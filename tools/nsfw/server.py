#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
自建图片内容检测服务

给彩虹外链网盘的「图片违规检测 → 自建检测服务」用。只监听本机回环地址，
由 PHP 那边 curl 过来，图片不出服务器，也不产生任何云服务调用费。

依赖只有三个：onnxruntime、pillow、numpy。没有 web 框架，标准库的
ThreadingHTTPServer 足够——这个服务的并发量就等于站点的图片上传频率。

接口：
    POST /check   {"path": "/绝对路径"} 或 {"url": "http://..."}
                  → {"ok":true,"score":0.93,"verdict":"block","detail":{...}}
    GET  /health  → 模型加载状态

模型不随代码分发，自己下载后在 config.json 里填路径，见同目录 README.md。
"""

import io
import json
import os
import sys
import time
import threading
import traceback
import urllib.request
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

    def check(self, payload):
        started = time.time()
        # 阈值以调用方（后台设置）为准，config.json 里的只是没传时的兜底，
        # 这样站长在后台调完立刻生效，不用重启这个服务
        block = float(payload.get('block', self.block))
        review = float(payload.get('review', self.review))
        img = self.load_image(payload)
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
            # 真人和二次元两个模型取较高分：一张图只要有一个模型认为有问题就算命中
            score = max(score, one)

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
        if self.path.split('?')[0] != '/health':
            return self._send(404, {'ok': False, 'msg': 'not found'})
        self._send(200, {
            'ok': True,
            'models': [{'name': m.name, 'kind': m.kind, 'size': list(m.size)}
                       for m in self.server.detector.models],
            'block_threshold': self.server.detector.block,
            'review_threshold': self.server.detector.review,
        })

    def do_POST(self):
        if self.path.split('?')[0] != '/check':
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
            self._send(200, self.server.detector.check(payload))
        except ValueError as exc:
            self._send(400, {'ok': False, 'msg': str(exc)})
        except Exception as exc:
            log('check 异常：%s\n%s' % (exc, traceback.format_exc()))
            self._send(500, {'ok': False, 'msg': str(exc)})


def scan(detector, target):
    """
    离线打分模式，用来定阈值。

    拿你自己站上的图跑一遍，看正常图打多少分、违规图打多少分，再把后台那两个
    阈值卡在中间——比照抄一个默认值靠谱得多，不同站点的图片分布差别很大。
    """
    exts = ('.jpg', '.jpeg', '.png', '.webp', '.bmp', '.gif')
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
        try:
            res = detector.check({'path': path})
        except Exception as exc:
            print('%-8s %-9s %s  (%s)' % ('-', 'error', path, exc))
            continue
        scores.append(res['score'])
        print('%-8.4f %-9s %s' % (res['score'], res['verdict'], path))
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
    log('监听 http://%s:%d/  （Ctrl+C 退出）' % (host, port))
    try:
        httpd.serve_forever()
    except KeyboardInterrupt:
        log('已退出')


if __name__ == '__main__':
    main()
