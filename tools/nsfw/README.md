# 自建图片内容检测服务

给后台「图片检测设置 → 图片违规检测 → **自建检测服务（本机模型）**」用的。

图片不出服务器、没有调用费、不依赖任何云账号。代价是要在服务器上另跑一个 Python
进程，还要下几百 MB 的模型文件。**这是进阶选项**，嫌麻烦就继续用阿里云/腾讯云的接口。

## 它能做什么、不能做什么

- ✅ 检测图片里的色情内容，真人和二次元两套模型同时跑，取较高分
- ✅ 三档处置：高分直接屏蔽并记入违规公示，中间分标成「待审核」等你人工确认，低分放行
- ✅ 后台有独立的**图片检测记录**页，每张图的评分、各模型明细、耗时都能查
- ❌ **不检测视频**（视频要抽帧，得配队列，是下一步的事）
- ❌ 不检测暴恐、涉政等其它场景，只做色情这一类
- ❌ 不检测 exe/apk 这类可执行文件。想挡这个请在「文件上传设置」里配 `type_block`

准确率不如阿里云那种持续对抗更新的商用接口，误报漏报都会更多，所以务必用好
「转人工阈值」那一档，别一刀切封掉。

## 前置条件

网站程序要先升到 **DB_VERSION 1019**（新增了检测记录表）。传完更新包访问一次
`你的域名/install/update.php` 完成升级，否则后台看不到「图片检测记录」这一页。

## 硬件要求

| 配置 | 建议 |
| --- | --- |
| 1 核 1G | **不建议自建**，推理会把 CPU 打满拖垮网站，继续用云接口更划算 |
| 2 核 2G | 只开 photo 一个模型，用量化版（84MB），`threads` 设 1 |
| 4 核 4G 以上 | 两个模型都开，量化版或完整版都行，`threads` 设 2 |
| 8 核 8G 以上 | 两个模型都开完整版，`threads` 设 2~3 |

内存占用主要看模型：量化版 photo 约 300MB，完整版 anime 约 1GB，两个都开约 1.6~1.8GB。

## 一键安装（推荐）

把这个包解压到服务器上**网站目录之外**的任意位置（比如 `/root/`），然后：

```bash
cd pan-nsfw
bash install.sh
```

它会自动做完这些事：

1. 按机器的内存和核数挑模型档位（内存小就只装真人模型的量化版）
2. **确保 pip 可用** —— 很多精简系统只装了 python3 不带 pip，脚本会按系统类型用
   apt / dnf / yum / apk 装上，都不行再走 `ensurepip`，最后兜底用官方引导脚本
3. **建虚拟环境** `/opt/pan-nsfw/venv` 装依赖 —— Debian 12 / Ubuntu 24 起，往系统
   Python 里 `pip install` 会被 PEP 668 直接拒绝，虚拟环境把这个问题和「跟 apt 装的
   包打架」一起绕开了。实在建不了（缺 `python3-venv`）会自动退回系统安装并加上
   `--break-system-packages`
4. 下模型，直连 huggingface 不通自动换 `hf-mirror.com`，断点续传，重跑不会重下
5. 自动找出 PHP 的运行用户（www / www-data / nginx / apache），生成配置
6. **注册 systemd 服务并设为开机自启**，装完就已经在跑了
7. 自检，然后打印后台该怎么填

装完把解压出来的那份删掉即可，东西都装到 `/opt/pan-nsfw` 了。

常用参数：

```bash
bash install.sh --dry-run              # 先看看它要干什么，不动系统
bash install.sh --models both          # 强制两个模型都装（默认按内存自动决定）
bash install.sh --models photo         # 只装真人模型
bash install.sh --photo-size quant     # 真人模型用 84MB 的量化版
bash install.sh --user www-data        # PHP 用户识别错了就手动指定
bash install.sh --port 9500            # 换端口
bash install.sh --uninstall            # 卸载（模型文件保留）
```

日常维护：

```bash
systemctl status pan-nsfw       # 看状态
systemctl restart pan-nsfw      # 改完 config.json 重启
journalctl -u pan-nsfw -f       # 看实时日志
```

**脚本是幂等的** —— 重复跑不会重下模型，也不会覆盖已有的 `config.json`，
升级版本时直接重跑就行。

---

下面是手动安装的步骤，一键脚本跑不通的时候再看。

## 一、装依赖

```bash
apt install -y python3 python3-pip python3-venv     # Debian/Ubuntu
python3 -m venv /opt/pan-nsfw/venv
/opt/pan-nsfw/venv/bin/pip install -r requirements.txt
```

装不上就换国内源：

```bash
/opt/pan-nsfw/venv/bin/pip install -i https://pypi.tuna.tsinghua.edu.cn/simple -r requirements.txt
```

## 二、放代码和模型

**别放在网站目录里**，否则 `config.json` 会被人直接下载走。

```bash
mkdir -p /opt/pan-nsfw/models
cp server.py requirements.txt config.example.json /opt/pan-nsfw/
cd /opt/pan-nsfw/models
```

下模型（二选一或都下）：

```bash
# 二次元（wd-tagger v3，446MB）——同时要下标签表
wget -O anime.onnx https://huggingface.co/SmilingWolf/wd-swinv2-tagger-v3/resolve/main/model.onnx
wget -O selected_tags.csv https://huggingface.co/SmilingWolf/wd-swinv2-tagger-v3/resolve/main/selected_tags.csv

# 真人（ViT NSFW detector，量化版 84MB，小机器用这个）
wget -O photo.onnx https://huggingface.co/AdamCodd/vit-base-nsfw-detector/resolve/main/onnx/model_quantized.onnx
# 完整版 329MB，准确率略高：
# wget -O photo.onnx https://huggingface.co/AdamCodd/vit-base-nsfw-detector/resolve/main/onnx/model.onnx
```

国内服务器连 huggingface.co 可能很慢，把域名换成 `hf-mirror.com` 即可，路径完全一样。

wd-tagger 想换更小的权重也行，把 `wd-swinv2-tagger-v3` 换成 `wd-vit-tagger-v3`(361MB)
或 `wd-v1-4-moat-tagger-v2`(311MB)，**输入尺寸和布局是从模型文件里自动读的**，配置不用改。

## 三、改配置

```bash
cp config.example.json config.json
vi config.json
```

主要改每个模型的 `path` 和 `labels` 路径。其余字段：

| 字段 | 说明 |
| --- | --- |
| `host` / `port` | 默认只监听 `127.0.0.1:9012`。**不要改成 0.0.0.0**，除非你确定有防火墙 |
| `token` | 选填。同机器上还跑着别人的程序时设一个，要和后台填的一致 |
| `enabled` | 某个模型设 `false` 就不加载，小机器用来只开一个 |
| `threads` | 每个模型占几个核。必须显式设，默认会抢满所有核把网站拖垮 |
| `layout` | `nchw` / `nhwc`。**一般不用填** —— 优先从模型的输入形状判断，形状全是符号维时再看维度名字，都认不出来才用这个 |
| `rating_weights` | 二次元模型的分级权重，见下 |
| `log_requests` | 每次检测记一行日志，默认 `true`。日志量太大想关掉就设 `false` |
| `block_threshold` / `review_threshold` | **只是兜底**，实际生效的是后台设置里那两个，每次请求都会带过来 |

### rating_weights 怎么理解

二次元模型输出四档分级：`general` / `sensitive` / `questionable` / `explicit`。
最终评分取的是**加权后的最大值**（不是加权求和，求和会超过 1，阈值就没法按概率理解了）。

默认 `explicit: 1.0` / `questionable: 0.5` / `sensitive: 0`，意思是：露骨内容按原分算，
擦边内容打对折，性感但不露的完全不算。

**这里有个值得利用的性质**：只要让 `questionable` 的权重**低于后台的封禁阈值**，
一张图哪怕 100% 被判为擦边，算出来的分也够不到封禁线，**最多进人工复核，永远不会被
机器直接封掉**。想把擦边图也管起来，就把权重提到 0.7 左右（配合 0.88 的封禁阈值）；
想连性感图一起管，把 `sensitive` 调到 0.5。

改完要 `systemctl restart pan-nsfw` 才生效。

## 四、定阈值（重要）

别照抄默认值。不同站点的图片分布差很多，阈值拍脑袋定的结果通常是要么天天误封、
要么等于没开。两种办法：

**办法一：先扫一遍历史图**

```bash
/opt/pan-nsfw/venv/bin/python /opt/pan-nsfw/server.py /opt/pan-nsfw/config.json \
    --scan /www/wwwroot/你的站/file/ 2>/dev/null > /tmp/scan.txt

sort -rn /tmp/scan.txt | head -40        # 分数最高的 40 张，重点看这些是不是真该拦
awk '$1>0.55' /tmp/scan.txt | wc -l      # 有多少张会被拦或进人工
```

**办法二：先开成"只看不拦"**

后台把两个阈值都填 **2**（分数最高是 1，永远够不到），保存后传几张图，
去后台「图片检测记录」看真实评分。这个办法比扫全站快，而且能对着自己刚传的图确认。

看清楚正常图和违规图各自落在什么区间，再把线划在中间。起步值建议
**封禁 0.88 / 转人工 0.55**：封禁线偏高是为了少误封，人工线压得低是因为进待审
只是多点几下鼠标，成本远小于漏掉。

## 五、后台开启

系统设置 → 图片检测设置：

1. 「图片违规检测」选 **自建检测服务（本机模型）**
2. 「检测服务地址」留空即可（默认 `http://127.0.0.1:9012/check`）
3. 填上第四步定出来的两个阈值
4. 保存，然后传张图试试

**改阈值不用重启服务**，保存就生效（每次请求都会把当前值带过去）。只有改
`config.json` 里的权重、线程数才要 `systemctl restart pan-nsfw`。

## 六、日常怎么看

**后台 → 系统设置 → 图片检测记录**（侧栏外观下在「发信记录」下面一行）

每张图检测完都会留一条，**包括放行的**——只看被拦下的，永远不知道有多少该拦的漏了
过去。页面上能看到：24 小时内的检测/拦截/转人工/失败数、平均耗时、每张图的评分和
各模型明细，点「查看」直接弹出原图。可以按结果筛选、按文件名或 IP 搜索。

记录会一直堆（每传一张图一条），右上角有「清理 7 天前的放行记录」按钮，
**只清放行的，拦截和待审的永远保留**。

**命令行看实时日志：**

```bash
journalctl -u pan-nsfw -f
```

每张图一行，长这样：

```
check verdict=block  score=0.9312 anime=0.9310 photo=0.0210 88ms /www/wwwroot/站/file/abc123
```

挂着这个再去传图，是**验证整条链路通没通最直接的办法** —— 传了图但日志一点动静都
没有，说明后台没开或者地址填错了。

## 常见问题

**服务起来了但只加载了一个模型？** `curl http://127.0.0.1:9012/health` 看 `models`
数组里有几个。少了就看 `journalctl -u pan-nsfw -n 30 --no-pager` 里的「加载 xxx 失败」，
多半是模型文件没下完整。

**传图变慢了？** 本地存储下单张一般几百毫秒。明显更久多半是 `threads` 设太小、或者
模型用了完整版而机器扛不住。可以只开 photo 一个模型。

**检测服务挂了会怎样？** 上传照常，一律放行不拦。失败原因写在网站日志里，也会在
后台检测记录里记成「检测失败」。宁可漏检也不能误伤正常上传。

**为什么本地存储更快？** PHP 会把图片的绝对路径直接给检测服务，省掉一次 HTTP 回环。
用云存储时只能给 URL 让服务自己去抓，会慢一些，也会产生回源流量。

**怎么看误判？** 后台「文件管理」筛选「待审核文件」就是落在中间档的，误判的点「正常」
放出来。如果正常图老是进这一档，说明转人工阈值定低了。

**所有图分数都差不多（比如都在 0.5 上下）？** 这不正常，说明模型收到的输入不对
（预处理的通道顺序或归一化跟模型对不上）。把 `journalctl` 里几张不同类型图片的分数
贴出来对比一下，正常情况下不同图之间应该有明显区分度。
