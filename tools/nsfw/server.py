#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
自建图片/视频内容检测服务

给彩虹外链网盘的「违规检测 → 自建检测服务」用。只监听本机回环地址，
由 PHP 那边 curl 过来，文件不出服务器，也不产生任何云服务调用费。

依赖只有三个：onnxruntime、pillow、numpy。没有 web 框架，标准库的
ThreadingHTTPServer 足够——这个服务的并发量就等于站点的上传频率。
视频检测额外需要 ffmpeg（系统装的或 pip 的 imageio-ffmpeg 都行），
没有 ffmpeg 时图片检测照常工作，只是 /check_video 会明确返回不支持。

接口：
    POST /check       {"path": "/绝对路径"} 或 {"url": "http://..."}
                      → {"ok":true,"score":0.93,"verdict":"block","detail":{...}}
                      图片是同步的，几百毫秒就回来
    POST /check_video 同上再加 {"callback": "http://站点/green_cb.php", ...}
                      → {"ok":true,"job":"xxx"}  立刻返回，抽帧打分在后台线程里跑
                      跑完 POST 回 callback；PHP 也可以拿 job 来轮询
    GET  /result?job= → 查一个视频任务的状态/结果（回调丢了时的兜底）
    GET  /health      → 模型加载状态、ffmpeg 状态、队列深度

模型不随代码分发，自己下载后在 config.json 里填路径，见同目录 README.md。
"""

import base64
import io
import json
import os
import queue
import re
import shutil
import subprocess
import sys
import tempfile
import time
import threading
import traceback
import urllib.parse
import urllib.request
import uuid
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer

try:
    import numpy as np
    import onnxruntime as ort
    from PIL import Image
except ImportError as exc:  # pragma: no cover
    sys.stderr.write('缺少依赖：%s\n请先执行 pip install -r requirements.txt\n' % exc)
    sys.exit(1)

Image.MAX_IMAGE_PIXELS = 120_000_000  # 挡住解压炸弹，超大图直接报错而不是把内存吃光

HERE = os.path.dirname(os.path.abspath(__file__))
DEFAULT_CONFIG = os.path.join(HERE, 'config.json')


def log(msg):
    sys.stderr.write('[%s] %s\n' % (time.strftime('%Y-%m-%d %H:%M:%S'), msg))
    sys.stderr.flush()


def find_ffmpeg(configured=''):
    """
    找一个能用的 ffmpeg，返回 (路径, 版本号)，找不到返回 (None, 原因)。

    顺序是「配置里指定的 → PATH 里的 → pip 装的 imageio-ffmpeg」：系统有就用系统的，
    没有才用 pip 那份自带的静态二进制。PATH 里有 ffmpeg 也不能直接信——断掉的软链、
    阉割过的构建都可能存在，实际跑一次 -version 能返回才算数。
    """
    candidates = []
    if configured:
        candidates.append(configured)
    found = shutil.which('ffmpeg')
    if found:
        candidates.append(found)
    try:
        import imageio_ffmpeg
        candidates.append(imageio_ffmpeg.get_ffmpeg_exe())
    except Exception:
        pass  # 没装就算了，前面两个能用就行

    for exe in candidates:
        try:
            out = subprocess.run([exe, '-version'], stdout=subprocess.PIPE,
                                 stderr=subprocess.STDOUT, timeout=10)
        except Exception:
            continue
        if out.returncode != 0:
            continue
        first = (out.stdout or b'').decode('utf-8', 'replace').split('\n')[0]
        m = re.search(r'ffmpeg version (\S+)', first)
        return exe, (m.group(1) if m else '未知版本')
    if configured:
        return None, '配置里的 ffmpeg_path 跑不起来'
    return None, '系统里没有 ffmpeg，也没装 imageio-ffmpeg'


class Model(object):
    """
    一个 ONNX 分类模型。输入尺寸和 NCHW/NHWC 布局都从模型本身读出来，
    不写死——换成同系列的其它权重（比如 wd 的 vit / convnext）不用改代码。
    """

    def __init__(self, cfg):
        self.name = cfg['name']
        self.kind = cfg['kind']                      # anime | photo
        self.path = cfg['path']
        self.channel_order = cfg.get('channel_order', 'rgb')
        self.scale = float(cfg.get('scale', 1.0))    # 像素先乘这个数
        self.mean = cfg.get('mean', [0.0, 0.0, 0.0])
        self.std = cfg.get('std', [1.0, 1.0, 1.0])
        self.pad_color = tuple(cfg.get('pad_color', [255, 255, 255]))
        self.activation = cfg.get('activation', 'softmax')  # softmax | sigmoid
        self.labels_file = cfg.get('labels')
        self.nsfw_index = cfg.get('nsfw_index', 1)
        self.rating_weights = cfg.get('rating_weights', {
            'explicit': 1.0, 'questionable': 0.5, 'sensitive': 0.0, 'general': 0.0
        })
        self.threads = int(cfg.get('threads', 2))

        opts = ort.SessionOptions()
        # 必须显式限制线程数：默认会把所有核抢满，小机器上直接把网站拖垮
        opts.intra_op_num_threads = self.threads
        opts.inter_op_num_threads = 1
        opts.log_severity_level = 3
        self.sess = ort.InferenceSession(self.path, sess_options=opts,
                                         providers=['CPUExecutionProvider'])

        inp = self.sess.get_inputs()[0]
        self.input_name = inp.name
        shape = list(inp.shape)
        if len(shape) != 4:
            raise ValueError('%s: 只支持 4 维输入，实际是 %s' % (self.name, shape))
        # 动态维会是字符串或 None，取不到就用配置里的兜底尺寸
        fallback = int(cfg.get('size', 448))
        self.layout = self._resolve_layout(shape, cfg)
        if self.layout == 'nhwc':
            self.size = self._dim(shape[1], fallback), self._dim(shape[2], fallback)
        else:
            self.size = self._dim(shape[2], fallback), self._dim(shape[3], fallback)

        self.rating_idx = {}
        if self.labels_file:
            self._load_ratings()
        # onnxruntime 的 session 本身线程安全，但同一路径上并发跑没有收益，
        # 这里串行化，让并发靠多个请求排队而不是把 CPU 打满
        self.lock = threading.Lock()
        log('已加载 %s（%s）%s %dx%d threads=%d'
            % (self.name, self.kind, self.layout, self.size[0], self.size[1], self.threads))

    @staticmethod
    def _dim(value, fallback):
        return int(value) if isinstance(value, int) and value > 0 else fallback

    def _resolve_layout(self, shape, cfg):
        """
        判断输入是 NCHW 还是 NHWC，按可靠程度依次尝试：

        1. 通道维是写死的 3，直接看它在第几位；
        2. 全是动态维的模型（transformers 导出的 ViT 就是
           ['batch_size','num_channels','height','width']，四个全是字符串），
           退而看维度的名字；
        3. 都认不出来，用配置里的 layout，最后默认 NCHW——绝大多数模型都是这个。
        """
        if shape[3] == 3:
            return 'nhwc'
        if shape[1] == 3:
            return 'nchw'
        names = [str(x).lower() for x in shape]
        if 'channel' in names[3]:
            return 'nhwc'
        if 'channel' in names[1]:
            return 'nchw'
        layout = str(cfg.get('layout', 'nchw')).lower()
        if layout not in ('nchw', 'nhwc'):
            layout = 'nchw'
        log('%s: 输入维度全是动态的 %s，按 %s 处理（不对的话在配置里加 "layout"）'
            % (self.name, shape, layout))
        return layout

    def _load_ratings(self):
        """wd-tagger 的 selected_tags.csv：tag_id,name,category，category=9 的是分级标签"""
        import csv
        with open(self.labels_file, 'r', encoding='utf-8') as fh:
            for i, row in enumerate(csv.DictReader(fh)):
                if row.get('category') == '9':
                    self.rating_idx[row['name']] = i
        if not self.rating_idx:
            raise ValueError('%s: %s 里没找到 category=9 的分级标签'
                             % (self.name, self.labels_file))

    def preprocess(self, img):
        h, w = self.size
        # 补成正方形再缩放，直接 resize 会把画面拉变形，影响判分
        side = max(img.size)
        canvas = Image.new('RGB', (side, side), self.pad_color)
        canvas.paste(img, ((side - img.size[0]) // 2, (side - img.size[1]) // 2))
        canvas = canvas.resize((w, h), Image.BILINEAR)

        arr = np.asarray(canvas, dtype=np.float32)
        if self.channel_order == 'bgr':
            arr = arr[:, :, ::-1]
        arr = arr * self.scale
        mean = np.asarray(self.mean, dtype=np.float32)
        std = np.asarray(self.std, dtype=np.float32)
        if mean.any() or not np.allclose(std, 1.0):
            arr = (arr - mean) / std
        if self.layout == 'nchw':
            arr = arr.transpose(2, 0, 1)
        return np.ascontiguousarray(arr[np.newaxis, ...], dtype=np.float32)

    def run(self, img):
        data = self.preprocess(img)
        with self.lock:
            out = self.sess.run(None, {self.input_name: data})[0]
        out = np.asarray(out[0], dtype=np.float64)

        if self.activation == 'sigmoid':
            probs = 1.0 / (1.0 + np.exp(-out))
        else:
            shifted = out - out.max()
            exp = np.exp(shifted)
            probs = exp / exp.sum()

        if self.kind == 'anime':
            ratings = {k: float(probs[i]) for k, i in self.rating_idx.items()}
            # 加权取最大而不是加权求和：求和会超过 1，阈值就没法按概率理解了
            score = 0.0
            for name, weight in self.rating_weights.items():
                if weight and name in ratings:
                    score = max(score, ratings[name] * float(weight))
            return min(score, 1.0), ratings
        score = float(probs[self.nsfw_index])
        return score, {'nsfw': score, 'sfw': float(1.0 - score)}


class Detector(object):
    def __init__(self, cfg):
        self.block = float(cfg.get('block_threshold', 0.85))
        self.review = float(cfg.get('review_threshold', 0.60))
        self.max_pixels = int(cfg.get('max_pixels', 40_000_000))
        self.fetch_timeout = float(cfg.get('fetch_timeout', 10))
        self.max_bytes = int(cfg.get('max_fetch_bytes', 32 * 1024 * 1024))
        # 每次检测记一行日志，进 journalctl。想关掉设成 false
        self.log_requests = bool(cfg.get('log_requests', True))

        # ——— 视频 ———
        v = cfg.get('video', {}) or {}
        self.video_enabled = bool(v.get('enabled', True))
        self.v_interval = max(1.0, float(v.get('interval', 5)))        # 每隔几秒取一帧
        self.v_max_frames = max(1, int(v.get('max_frames', 40)))       # 一个视频最多取几帧
        self.v_max_duration = float(v.get('max_duration', 7200))       # 超过这个时长的不跑，直接转人工
        self.v_hit_frames = max(1, int(v.get('hit_frames', 2)))        # 要几帧命中才判封禁
        self.v_single = float(v.get('single_frame_block', 0.97))       # 单帧高到这个分，一帧也算数
        self.v_frame_timeout = float(v.get('frame_timeout', 20))       # 单帧抽取超时
        self.v_probe_timeout = float(v.get('probe_timeout', 20))       # 探测时长超时
        self.v_shot_size = int(v.get('shot_max_size', 480))            # 回传证据帧的最长边
        self.v_frame_width = int(v.get('frame_width', 640))            # 抽帧时缩到多宽再进模型
        # 小于这个大小的远程视频先整个下下来再抽帧，见 maybe_cache()。0 为始终走 Range
        self.v_cache_bytes = int(v.get('cache_max_bytes', 64 * 1024 * 1024))
        self.ffmpeg = None
        self.ffmpeg_version = ''
        if self.video_enabled:
            exe, info = find_ffmpeg(cfg.get('ffmpeg_path', ''))
            if exe:
                self.ffmpeg, self.ffmpeg_version = exe, info
                log('ffmpeg：%s（%s）' % (exe, info))
            else:
                log('视频检测不可用：%s。图片检测不受影响' % info)

        self.models = []
        for mc in cfg.get('models', []):
            if not mc.get('enabled', True):
                continue
            if not os.path.isfile(mc['path']):
                log('跳过 %s：模型文件不存在 %s' % (mc.get('name'), mc['path']))
                continue
            try:
                self.models.append(Model(mc))
            except Exception as exc:
                log('加载 %s 失败：%s' % (mc.get('name'), exc))
        if not self.models:
            log('警告：一个模型都没加载成功，服务会对所有图片返回 pass')

    def load_image(self, payload):
        path = payload.get('path')
        if path:
            if not os.path.isfile(path):
                raise ValueError('文件不存在：%s' % path)
            with open(path, 'rb') as fh:
                raw = fh.read(self.max_bytes + 1)
        else:
            url = payload.get('url')
            if not url:
                raise ValueError('缺少 path 或 url')
            if not url.startswith(('http://', 'https://')):
                raise ValueError('url 只支持 http/https')
            req = urllib.request.Request(url, headers={'User-Agent': 'pan-nsfw/1.0'})
            with urllib.request.urlopen(req, timeout=self.fetch_timeout) as resp:
                raw = resp.read(self.max_bytes + 1)
        if len(raw) > self.max_bytes:
            raise ValueError('图片超过 %d 字节，已跳过' % self.max_bytes)

        img = Image.open(io.BytesIO(raw))
        # 动图只看第一帧，够用而且省掉整段解码
        img.seek(0) if getattr(img, 'is_animated', False) else None
        if img.width * img.height > self.max_pixels:
            raise ValueError('图片像素过大（%dx%d）' % (img.width, img.height))
        return img.convert('RGB')

    def score_image(self, img):
        """
        把一张图交给所有模型，返回 (较高的那个分, 每个模型的明细, 每个模型的分)。
        图片走一次，视频的每一帧也走这里，判分口径必须完全一致。
        """
        detail = {}
        each = {}
        score = 0.0
        for model in self.models:
            try:
                one, info = model.run(img)
            except Exception as exc:
                log('%s 推理失败：%s' % (model.name, exc))
                detail[model.name] = {'error': str(exc)}
                continue
            detail[model.name] = info
            each[model.name] = one
            # 真人和二次元两个模型取较高分：只要有一个模型认为有问题就算命中
            score = max(score, one)
        return score, detail, each

    def check(self, payload):
        started = time.time()
        # 阈值以调用方（后台设置）为准，config.json 里的只是没传时的兜底，
        # 这样站长在后台调完立刻生效，不用重启这个服务
        block = float(payload.get('block', self.block))
        review = float(payload.get('review', self.review))
        img = self.load_image(payload)
        score, detail, each = self.score_image(img)

        verdict = 'pass'
        if score >= block:
            verdict = 'block'
        elif score >= review:
            verdict = 'review'

        if self.log_requests:
            # 每张图记一行，这就是「检测记录」：journalctl -u pan-nsfw 能实时看
            # 定阈值、查漏判误判全靠它，所以默认开着
            log('check verdict=%-6s score=%.4f %s %dms %s'
                % (verdict, score,
                   ' '.join('%s=%.4f' % (k, v) for k, v in sorted(each.items())),
                   int((time.time() - started) * 1000),
                   payload.get('path') or payload.get('url') or '-'))
        return {
            'ok': True,
            'score': round(score, 4),
            'verdict': verdict,
            'detail': detail,
            'ms': int((time.time() - started) * 1000),
        }

    # ————————————————— 以下是视频 —————————————————

    def video_source(self, payload):
        """本地存储给绝对路径，云存储给一个直链 URL，两种都直接交给 ffmpeg。"""
        path = payload.get('path')
        if path:
            if not os.path.isfile(path):
                raise ValueError('文件不存在：%s' % path)
            return path
        url = payload.get('url')
        if not url:
            raise ValueError('缺少 path 或 url')
        if not url.startswith(('http://', 'https://')):
            raise ValueError('url 只支持 http/https')
        return url

    def remote_size(self, url):
        """HEAD 一下拿文件大小，拿不到返回 0（有些存储不给 Content-Length）。"""
        try:
            req = urllib.request.Request(url, method='HEAD',
                                         headers={'User-Agent': 'pan-nsfw/1.0'})
            with urllib.request.urlopen(req, timeout=self.fetch_timeout) as resp:
                return int(resp.headers.get('Content-Length') or 0)
        except Exception:
            return 0

    def maybe_cache(self, src):
        """
        远程的小文件先整个拉到本地再抽帧，返回 (用来抽帧的地址, 要删的临时文件)。

        ffmpeg 的 -ss 定位走 HTTP Range，不会顺序下完整个文件，但每定位一次仍然要拉
        1~2MB（实测 720p 2Mbps 的片子，和定位到哪儿基本无关）。40 帧就是几十 MB，
        对小视频来说比整个下一遍还贵——所以小于阈值的先下下来，大文件继续走 Range。
        """
        if not src.startswith(('http://', 'https://')) or self.v_cache_bytes <= 0:
            return src, None
        size = self.remote_size(src)
        if size <= 0 or size > self.v_cache_bytes:
            return src, None
        fd, tmp = tempfile.mkstemp(prefix='pan-nsfw-', suffix='.bin')
        try:
            req = urllib.request.Request(src, headers={'User-Agent': 'pan-nsfw/1.0'})
            with urllib.request.urlopen(req, timeout=self.fetch_timeout) as resp:
                with os.fdopen(fd, 'wb') as fh:
                    got = 0
                    while True:
                        buf = resp.read(1024 * 256)
                        if not buf:
                            break
                        got += len(buf)
                        if got > self.v_cache_bytes:
                            raise ValueError('实际大小超过缓存上限')
                        fh.write(buf)
            return tmp, tmp
        except Exception as exc:
            # 下不下来不算错误，回退到 Range 直读就是了
            log('缓存远程视频失败，改走 Range 直读：%s' % exc)
            try:
                os.unlink(tmp)
            except Exception:
                pass
            return src, None

    def probe_duration(self, src):
        """
        读视频时长。故意用 ffmpeg 而不是 ffprobe：pip 装的 imageio-ffmpeg 只带
        ffmpeg 一个二进制，没有 ffprobe，用它就会在「系统没装 ffmpeg」的机器上失灵。
        ffmpeg 不给输出文件时会以非 0 退出，时长在 stderr 里，照样能拿到。
        拿不到时长不算错误（有些流式 mp4 就是没有），回 0 交给上面按未知处理。
        """
        try:
            out = subprocess.run([self.ffmpeg, '-nostdin', '-i', src],
                                 stdout=subprocess.DEVNULL, stderr=subprocess.PIPE,
                                 timeout=self.v_probe_timeout)
        except Exception as exc:
            log('探测时长失败：%s' % exc)
            return 0.0
        text = (out.stderr or b'').decode('utf-8', 'replace')
        m = re.search(r'Duration:\s*(\d+):(\d+):(\d+(?:\.\d+)?)', text)
        if not m:
            return 0.0
        return int(m.group(1)) * 3600 + int(m.group(2)) * 60 + float(m.group(3))

    def sample_times(self, duration, interval, max_frames):
        """
        算出要在哪几个时间点取帧。掐头去尾各 2%：片头片尾经常是黑帧和 logo，
        白跑几帧没意义。时长未知就按固定间隔一路往后取，取不出来了自然停。
        """
        if duration <= 0:
            return [i * interval for i in range(max_frames)]
        count = int(duration // interval)
        count = max(1, min(max_frames, count))
        if count == 1:
            return [duration / 2.0]
        start = duration * 0.02
        span = duration * 0.96
        return [start + span * i / (count - 1) for i in range(count)]

    def grab_frame(self, src, at):
        """
        取某个时间点的一帧。-ss 放在 -i 前面是输入侧快速定位：本地文件直接跳，
        HTTP 源走 Range 只拉这一段附近的数据——不这样做的话顺序解码一个 2G 的视频
        就等于把 2G 全下一遍，云存储的流量能吃穷你。
        取不到返回 None（超出结尾、坏帧都算），不抛异常。
        """
        cmd = [self.ffmpeg, '-nostdin', '-loglevel', 'error',
               '-ss', '%.3f' % max(0.0, at), '-i', src,
               '-frames:v', '1', '-an', '-sn',
               '-vf', "scale='min(%d,iw)':-2" % self.v_frame_width,
               '-f', 'image2pipe', '-vcodec', 'mjpeg', '-q:v', '3', '-']
        try:
            out = subprocess.run(cmd, stdout=subprocess.PIPE, stderr=subprocess.PIPE,
                                 timeout=self.v_frame_timeout)
        except subprocess.TimeoutExpired:
            log('抽帧超时 @%.1fs' % at)
            return None
        except Exception as exc:
            log('抽帧失败 @%.1fs：%s' % (at, exc))
            return None
        if not out.stdout:
            return None
        try:
            return Image.open(io.BytesIO(out.stdout)).convert('RGB')
        except Exception:
            return None

    def make_shot(self, img):
        """把命中的那一帧压成 base64 jpeg 回传，后台人工复核时直接看这张就够了。"""
        try:
            shot = img.copy()
            shot.thumbnail((self.v_shot_size, self.v_shot_size))
            buf = io.BytesIO()
            shot.save(buf, 'JPEG', quality=78)
            return base64.b64encode(buf.getvalue()).decode('ascii')
        except Exception as exc:
            log('生成证据帧失败：%s' % exc)
            return ''

    def check_video(self, payload):
        """
        视频判定：抽若干帧逐帧打分，再聚合成一个视频级结论。

        聚合口径是这里最要紧的地方，不能照搬图片的单帧阈值——转场、肤色、泳装剧照
        都可能让某一帧飙到 0.9，只看最高分会误判到没法用。所以要「够几帧命中」才封，
        单帧命中只转人工；除非某一帧高到离谱（single_frame_block），那一帧也算数。
        """
        started = time.time()
        if not self.ffmpeg:
            raise ValueError('服务端没有可用的 ffmpeg，视频检测不可用')
        # 阈值和抽帧参数都以调用方（后台设置）为准，config.json 里的只是没传时的兜底，
        # 站长在后台调完立刻生效，不用重启这个服务
        block = float(payload.get('block', self.block))
        review = float(payload.get('review', self.review))
        hit_need = max(1, int(payload.get('hit_frames', self.v_hit_frames)))
        interval = max(1.0, float(payload.get('interval', self.v_interval)))
        max_frames = max(1, int(payload.get('max_frames', self.v_max_frames)))
        max_duration = float(payload.get('max_duration', self.v_max_duration))
        src = self.video_source(payload)

        # 远程的小文件先整个拉到本地，比一帧定位一次划算，跑完必须删掉
        src, tmpfile = self.maybe_cache(src)
        try:
            res = self.scan_video(src, block, review, hit_need, interval, max_frames, max_duration)
        finally:
            if tmpfile:
                try:
                    os.unlink(tmpfile)
                except Exception:
                    pass

        res['ms'] = int((time.time() - started) * 1000)
        if self.log_requests:
            log('video verdict=%-6s score=%.4f 命中%d/%d帧 @%.1fs %dms %s'
                % (res['verdict'], res['score'], res['hits'], res['scored'], res['hit_at'],
                   res['ms'], payload.get('path') or payload.get('url') or '-'))
        return res

    def scan_video(self, src, block, review, hit_need, interval, max_frames, max_duration):
        """真正抽帧打分的那一段。src 到这里已经是本地路径或可 Range 的地址了。"""
        duration = self.probe_duration(src)
        if max_duration > 0 and duration > max_duration:
            # 太长的片子跑完要几分钟，与其占满队列不如直接交给人工
            return {
                'ok': True, 'score': 0.0, 'verdict': 'review', 'duration': round(duration, 1),
                'frames': 0, 'scored': 0, 'hits': 0, 'hit_at': 0, 'shot': '',
                'detail': {}, 'msg': '时长 %.0f 秒超过上限，转人工' % duration,
            }

        times = self.sample_times(duration, interval, max_frames)
        top = 0.0
        top_at = 0.0
        top_img = None
        hits = 0
        scored = 0
        misses = 0
        bymodel = {}
        for at in times:
            img = self.grab_frame(src, at)
            if img is None:
                misses += 1
                # 时长未知时是靠取不到帧来判断结尾的；连着三次取不到就当到头了
                if misses >= 3:
                    break
                continue
            misses = 0
            scored += 1
            one, _detail, each = self.score_image(img)
            for name, value in each.items():
                bymodel[name] = max(bymodel.get(name, 0.0), value)
            if one > top:
                top, top_at, top_img = one, at, img
            if one >= block:
                hits += 1
                # 已经够判封禁了，剩下的帧不用再跑——明显违规的视频能省下大半时间
                if hits >= hit_need:
                    break

        if scored == 0:
            raise ValueError('一帧都没取到，可能是编码不支持或文件损坏')

        verdict = 'pass'
        if hits >= hit_need or top >= self.v_single:
            verdict = 'block'
        elif top >= review:
            verdict = 'review'

        return {
            'ok': True,
            'score': round(top, 4),
            'verdict': verdict,
            'duration': round(duration, 1),
            'frames': len(times),
            'scored': scored,
            'hits': hits,
            'hit_at': round(top_at, 1),
            # 证据帧只在有必要看的时候带上，放行的视频没人会去翻，白白撑大回调体积
            'shot': self.make_shot(top_img) if (verdict != 'pass' and top_img is not None) else '',
            'detail': dict((k, round(v, 4)) for k, v in bymodel.items()),
        }


class VideoJobs(object):
    """
    视频任务队列。一个视频要抽几十帧、跑两套模型，十几秒起步，绝不能卡在 PHP 的
    上传请求里，所以提交完立刻返回 job_id，真正的活在 worker 线程里干。

    干完两条路通知 PHP：主动 POST 回 callback，以及 PHP 拿 job_id 来 /result 轮询。
    两条都要，因为站点是「先挂起再放行」——回调要是丢了，文件会永远卡在待审状态。
    """

    def __init__(self, detector, cfg):
        v = cfg.get('video', {}) or {}
        self.detector = detector
        self.token = cfg.get('token', '')
        self.result_ttl = float(v.get('result_ttl', 3600))
        self.callback_timeout = float(v.get('callback_timeout', 10))
        self.callback_retry = int(v.get('callback_retry', 3))
        # worker 默认只开 1 个：CPU 上一帧一两百毫秒，开多了只会互相抢核，
        # 队列长度到顶就直接拒绝，让 PHP 按「检测不可用」处理，别把请求堆死在这儿
        self.workers = max(1, int(v.get('workers', 1)))
        self.queue = queue.Queue(max(1, int(v.get('queue_size', 64))))
        self.jobs = {}
        self.lock = threading.Lock()
        for i in range(self.workers):
            t = threading.Thread(target=self._worker, name='video-%d' % i, daemon=True)
            t.start()

    def submit(self, payload):
        job_id = uuid.uuid4().hex
        item = {'job': job_id, 'status': 'queued', 'addtime': time.time(),
                'result': None, 'msg': ''}
        with self.lock:
            self._gc()
            self.jobs[job_id] = item
        try:
            self.queue.put_nowait((job_id, payload))
        except queue.Full:
            with self.lock:
                self.jobs.pop(job_id, None)
            raise ValueError('检测队列已满，稍后再试')
        return job_id

    def get(self, job_id):
        with self.lock:
            return self.jobs.get(job_id)

    def depth(self):
        return self.queue.qsize()

    def _gc(self):
        """结果留一段时间够 PHP 轮询到就行，不然长期跑下来 jobs 会一直涨。"""
        if self.result_ttl <= 0:
            return
        deadline = time.time() - self.result_ttl
        for key in [k for k, v in self.jobs.items()
                    if v['status'] in ('done', 'error') and v['addtime'] < deadline]:
            self.jobs.pop(key, None)

    def _worker(self):
        while True:
            job_id, payload = self.queue.get()
            with self.lock:
                item = self.jobs.get(job_id)
                if item:
                    item['status'] = 'running'
            try:
                result = self.detector.check_video(payload)
                status, msg = 'done', ''
            except Exception as exc:
                result, status, msg = None, 'error', str(exc)
                log('视频检测失败（%s）：%s' % (job_id, exc))
            with self.lock:
                item = self.jobs.get(job_id)
                if item:
                    item['status'] = status
                    item['result'] = result
                    item['msg'] = msg
            self._callback(payload.get('callback'), job_id, status, result, msg)
            self.queue.task_done()

    def _callback(self, url, job_id, status, result, msg):
        if not url or not url.startswith(('http://', 'https://')):
            return
        body = {'job': job_id, 'status': status, 'msg': msg}
        if result:
            body.update(result)
        data = json.dumps(body, ensure_ascii=False).encode('utf-8')
        headers = {'Content-Type': 'application/json; charset=utf-8',
                   'User-Agent': 'pan-nsfw/1.0'}
        if self.token:
            headers['X-Auth-Token'] = self.token
        for i in range(max(1, self.callback_retry)):
            try:
                req = urllib.request.Request(url, data=data, headers=headers, method='POST')
                with urllib.request.urlopen(req, timeout=self.callback_timeout) as resp:
                    if 200 <= resp.status < 300:
                        return
                    log('回调返回 HTTP %s（%s）' % (resp.status, job_id))
            except Exception as exc:
                log('回调失败第 %d 次（%s）：%s' % (i + 1, job_id, exc))
            time.sleep(2 * (i + 1))
        # 三次都没通就不管了：PHP 那边有轮询兜底，会自己来 /result 拿结果
        log('回调彻底失败（%s），等 PHP 轮询' % job_id)


class Handler(BaseHTTPRequestHandler):
    server_version = 'pan-nsfw/1.0'
    protocol_version = 'HTTP/1.1'

    def log_message(self, fmt, *args):
        pass  # 默认会把每条请求打到 stderr，太吵

    def _send(self, code, obj):
        body = json.dumps(obj, ensure_ascii=False).encode('utf-8')
        self.send_response(code)
        self.send_header('Content-Type', 'application/json; charset=utf-8')
        self.send_header('Content-Length', str(len(body)))
        self.end_headers()
        self.wfile.write(body)

    def _authed(self):
        token = self.server.token
        if not token:
            return True
        return self.headers.get('X-Auth-Token') == token

    def do_GET(self):
        path = self.path.split('?')[0]
        detector = self.server.detector
        if path == '/health':
            return self._send(200, {
                'ok': True,
                'models': [{'name': m.name, 'kind': m.kind, 'size': list(m.size)}
                           for m in detector.models],
                'block_threshold': detector.block,
                'review_threshold': detector.review,
                # 后台设置页读这三个字段来显示「视频检测：可用/不可用」。
                # 开关打得开、实际永远不工作，是最难发现的一种坏法
                'video': bool(detector.video_enabled and detector.ffmpeg),
                'ffmpeg': detector.ffmpeg_version if detector.ffmpeg else '',
                'queue': self.server.jobs.depth() if self.server.jobs else 0,
            })
        if path == '/result':
            if not self._authed():
                return self._send(403, {'ok': False, 'msg': 'bad token'})
            if not self.server.jobs:
                return self._send(400, {'ok': False, 'msg': '视频检测未启用'})
            query = urllib.parse.parse_qs(urllib.parse.urlparse(self.path).query)
            job_id = (query.get('job') or [''])[0]
            item = self.server.jobs.get(job_id)
            if not item:
                # 结果过期或服务重启过。PHP 那边按「查不到」重新提交或走超时放行
                return self._send(404, {'ok': False, 'msg': '没有这个任务（可能已过期）'})
            body = {'ok': True, 'job': job_id, 'status': item['status'], 'msg': item['msg']}
            if item['result']:
                body.update(item['result'])
            return self._send(200, body)
        self._send(404, {'ok': False, 'msg': 'not found'})

    def do_POST(self):
        path = self.path.split('?')[0]
        if path not in ('/check', '/check_video'):
            return self._send(404, {'ok': False, 'msg': 'not found'})
        if not self._authed():
            return self._send(403, {'ok': False, 'msg': 'bad token'})
        try:
            length = int(self.headers.get('Content-Length') or 0)
            if length <= 0 or length > 65536:
                raise ValueError('请求体长度不合法')
            payload = json.loads(self.rfile.read(length).decode('utf-8'))
        except Exception as exc:
            return self._send(400, {'ok': False, 'msg': '请求解析失败：%s' % exc})
        try:
            if path == '/check':
                return self._send(200, self.server.detector.check(payload))
            # 视频：只把任务放进队列就回，抽帧打分在 worker 线程里慢慢跑
            detector = self.server.detector
            if not self.server.jobs or not detector.video_enabled:
                return self._send(200, {'ok': False, 'unsupported': True, 'msg': '视频检测未启用'})
            if not detector.ffmpeg:
                return self._send(200, {'ok': False, 'unsupported': True,
                                        'msg': '服务端没有可用的 ffmpeg'})
            self._send(200, {'ok': True, 'job': self.server.jobs.submit(payload),
                             'queue': self.server.jobs.depth()})
        except ValueError as exc:
            self._send(400, {'ok': False, 'msg': str(exc)})
        except Exception as exc:
            log('%s 异常：%s\n%s' % (path, exc, traceback.format_exc()))
            self._send(500, {'ok': False, 'msg': str(exc)})


def scan(detector, target):
    """
    离线打分模式，用来定阈值。

    拿你自己站上的图跑一遍，看正常图打多少分、违规图打多少分，再把后台那两个
    阈值卡在中间——比照抄一个默认值靠谱得多，不同站点的图片分布差别很大。
    """
    exts = ('.jpg', '.jpeg', '.png', '.webp', '.bmp', '.gif',
            '.mp4', '.mkv', '.mov', '.avi', '.webm', '.flv', '.wmv', '.ts', '.m4v', '.mpg')
    videos = ('.mp4', '.mkv', '.mov', '.avi', '.webm', '.flv', '.wmv', '.ts', '.m4v', '.mpg')
    files = []
    if os.path.isdir(target):
        for root, _dirs, names in os.walk(target):
            for n in sorted(names):
                if os.path.splitext(n)[1].lower() in exts:
                    files.append(os.path.join(root, n))
    else:
        files.append(target)
    if not files:
        log('没找到图片：%s' % target)
        return

    print('%-8s %-9s %s' % ('score', 'verdict', 'file'))
    scores = []
    for path in files:
        is_video = os.path.splitext(path)[1].lower() in videos
        try:
            # 视频在这里是同步跑的（不走队列），本来就是拿来慢慢跑一批片子定阈值的
            res = detector.check_video({'path': path}) if is_video else detector.check({'path': path})
        except Exception as exc:
            print('%-8s %-9s %s  (%s)' % ('-', 'error', path, exc))
            continue
        scores.append(res['score'])
        extra = ''
        if is_video:
            extra = '  命中%d/%d帧 @%.0fs' % (res['hits'], res['scored'], res['hit_at'])
        print('%-8.4f %-9s %s%s' % (res['score'], res['verdict'], path, extra))
    if scores:
        scores.sort()
        print('')
        print('共 %d 张：最低 %.4f  中位 %.4f  最高 %.4f'
              % (len(scores), scores[0], scores[len(scores) // 2], scores[-1]))


def main():
    args = list(sys.argv[1:])
    scan_target = None
    if '--scan' in args:
        i = args.index('--scan')
        if i + 1 >= len(args):
            sys.stderr.write('--scan 后面要跟一个图片文件或目录\n')
            sys.exit(1)
        scan_target = args[i + 1]
        del args[i:i + 2]

    cfg_path = args[0] if args else DEFAULT_CONFIG
    if not os.path.isfile(cfg_path):
        sys.stderr.write('找不到配置文件 %s，可以先复制 config.example.json\n' % cfg_path)
        sys.exit(1)
    with open(cfg_path, 'r', encoding='utf-8') as fh:
        cfg = json.load(fh)

    if scan_target:
        scan(Detector(cfg), scan_target)
        return

    host = cfg.get('host', '127.0.0.1')
    port = int(cfg.get('port', 9012))
    if host not in ('127.0.0.1', '::1', 'localhost'):
        log('注意：正在监听 %s，不是回环地址。暴露到公网前务必设置 token 或加防火墙' % host)

    httpd = ThreadingHTTPServer((host, port), Handler)
    httpd.daemon_threads = True
    httpd.token = cfg.get('token', '')
    httpd.detector = Detector(cfg)
    # 没有 ffmpeg 就不起 worker 线程，/check_video 会明确回一个「不支持」，
    # 让站长在后台设置页上看得见，而不是任务默默排队排到天荒地老
    httpd.jobs = VideoJobs(httpd.detector, cfg) if httpd.detector.ffmpeg else None
    log('监听 http://%s:%d/  （Ctrl+C 退出）' % (host, port))
    try:
        httpd.serve_forever()
    except KeyboardInterrupt:
        log('已退出')


if __name__ == '__main__':
    main()
