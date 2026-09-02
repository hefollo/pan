#!/usr/bin/env bash
#
# 自建图片检测服务 一键安装
#
#   bash install.sh                 # 按机器配置自动挑模型，装完自动开机自启
#   bash install.sh --dry-run       # 只打印要做什么，不动系统
#   bash install.sh --models photo --photo-size quant   # 手动指定
#   bash install.sh --uninstall     # 卸载（模型文件保留）
#
# 干的事：装依赖 → 下模型 → 生成配置 → 注册 systemd 开机自启 → 自检
# 重复执行是安全的，已经下好的模型不会重下。

set -u

DIR=/opt/pan-nsfw
PORT=9012
MODELS=auto            # auto | both | anime | photo
PHOTO_SIZE=auto        # auto | full | quant
RUN_USER=""
MIRROR=""              # 留空自动判断用不用 hf-mirror
DRY=0
UNINSTALL=0
SERVICE=pan-nsfw

RED=$'\033[31m'; GREEN=$'\033[32m'; YELLOW=$'\033[33m'; DIM=$'\033[2m'; OFF=$'\033[0m'
info(){ echo "${GREEN}==>${OFF} $*"; }
warn(){ echo "${YELLOW}[!]${OFF} $*"; }
die(){ echo "${RED}[x]${OFF} $*" >&2; exit 1; }
run(){ if [ "$DRY" = 1 ]; then echo "${DIM}    \$ $*${OFF}"; else eval "$@"; fi; }

while [ $# -gt 0 ]; do
	case "$1" in
		--dir) DIR=$2; shift 2;;
		--port) PORT=$2; shift 2;;
		--models) MODELS=$2; shift 2;;
		--photo-size) PHOTO_SIZE=$2; shift 2;;
		--user) RUN_USER=$2; shift 2;;
		--mirror) MIRROR=$2; shift 2;;
		--service) SERVICE=$2; shift 2;;
		--dry-run) DRY=1; shift;;
		--uninstall) UNINSTALL=1; shift;;
		-h|--help) sed -n '2,11p' "$0" | sed 's/^# \{0,1\}//'; exit 0;;
		*) die "不认识的参数：$1（用 --help 看用法）";;
	esac
done

# 参数先校验，别等跑到一半才报错
case "$MODELS" in auto|both|anime|photo) : ;; *) die "--models 只能是 both / anime / photo";; esac
case "$PHOTO_SIZE" in auto|full|quant) : ;; *) die "--photo-size 只能是 full / quant";; esac

[ "$DRY" = 1 ] && warn "演练模式：只打印不执行"

# ---------------------------------------------------------------- 卸载
if [ "$UNINSTALL" = 1 ]; then
	info "停止并移除 $SERVICE"
	run "systemctl disable --now $SERVICE 2>/dev/null || true"
	run "rm -f /etc/systemd/system/$SERVICE.service"
	run "systemctl daemon-reload"
	echo
	info "已卸载。模型和配置还留在 $DIR，确认不要了再自己删："
	echo "    rm -rf $DIR"
	exit 0
fi

# ---------------------------------------------------------------- 环境检查
[ "$DRY" = 1 ] || [ "$(id -u)" = 0 ] || die "请用 root 运行（或加 sudo）"

PY=$(command -v python3 || true)
[ -n "$PY" ] || die "没找到 python3，请先安装：apt install -y python3 python3-pip"

PYVER=$("$PY" -c 'import sys;print("%d.%d"%sys.version_info[:2])' 2>/dev/null || echo 0.0)
case "$PYVER" in
	3.[89]|3.1[0-9]) : ;;
	*) warn "当前 python3 是 $PYVER，onnxruntime 一般要 3.8 以上，装不上的话请先升级";;
esac

DL=""
command -v curl >/dev/null 2>&1 && DL=curl
[ -z "$DL" ] && command -v wget >/dev/null 2>&1 && DL=wget
[ -n "$DL" ] || die "curl 和 wget 都没有，装一个：apt install -y curl"

# 内存和核数决定挑哪档模型，不问用户
MEM_MB=$(awk '/MemTotal/{printf "%d", $2/1024}' /proc/meminfo 2>/dev/null || echo 4096)
CORES=$(getconf _NPROCESSORS_ONLN 2>/dev/null || echo 2)
THREADS=$(( CORES / 4 )); [ "$THREADS" -lt 1 ] && THREADS=1; [ "$THREADS" -gt 3 ] && THREADS=3

if [ "$MODELS" = auto ]; then
	if [ "$MEM_MB" -lt 2048 ]; then
		MODELS=photo
		warn "内存只有 ${MEM_MB}MB，只装真人模型的量化版。这种配置其实更推荐直接用云接口"
	elif [ "$MEM_MB" -lt 6144 ]; then
		MODELS=photo
		info "内存 ${MEM_MB}MB，装真人模型（二次元模型要多 1G 内存，需要的话加 --models both）"
	else
		MODELS=both
	fi
fi
if [ "$PHOTO_SIZE" = auto ]; then
	if [ "$MEM_MB" -lt 4096 ]; then PHOTO_SIZE=quant; else PHOTO_SIZE=full; fi
fi

# PHP 跑在哪个用户下，检测服务就得跑在哪个用户下，否则读不了 file/ 目录里的图
if [ -z "$RUN_USER" ]; then
	for u in www www-data nginx apache; do
		id "$u" >/dev/null 2>&1 && RUN_USER=$u && break
	done
	[ -n "$RUN_USER" ] || RUN_USER=root
fi
id "$RUN_USER" >/dev/null 2>&1 || die "用户 $RUN_USER 不存在，用 --user 指定 PHP 的运行用户"

# 国内机器直连 huggingface 基本没戏，探一下再决定走不走镜像
if [ -z "$MIRROR" ]; then
	if [ "$DL" = curl ]; then
		curl -sSfI --max-time 6 https://huggingface.co/ >/dev/null 2>&1 && MIRROR=huggingface.co || MIRROR=hf-mirror.com
	else
		wget -q --spider --timeout=6 https://huggingface.co/ >/dev/null 2>&1 && MIRROR=huggingface.co || MIRROR=hf-mirror.com
	fi
fi

echo
info "安装目录   $DIR"
info "监听端口   127.0.0.1:$PORT"
info "运行用户   $RUN_USER"
info "机器配置   ${MEM_MB}MB 内存 / ${CORES} 核 → 模型 $MODELS，真人模型用 $PHOTO_SIZE，每模型 $THREADS 线程"
info "下载源     $MIRROR"
echo

# ---------------------------------------------------------------- 装依赖
info "安装 Python 依赖"
SRC_DIR=$(cd "$(dirname "$0")" && pwd)
run "mkdir -p '$DIR/models'"
for f in server.py requirements.txt config.example.json README.md; do
	[ -f "$SRC_DIR/$f" ] || die "缺少 $SRC_DIR/$f，请把整个包解压后再运行"
	run "cp -f '$SRC_DIR/$f' '$DIR/'"
done

PIP_OK=0
if [ "$DRY" = 1 ]; then
	echo "${DIM}    \$ 确保 pip 可用（没有就用 apt/dnf/yum 或 ensurepip 装上）${OFF}"
	echo "${DIM}    \$ 建虚拟环境 $DIR/venv 并在里面装 onnxruntime pillow numpy${OFF}"
	PYBIN="$DIR/venv/bin/python"
	PIP_OK=1
else
	# ---- 1. 先把 pip 弄出来 ----
	# 很多精简系统只装了 python3 没装 pip，直接报 "No module named pip"
	if ! "$PY" -m pip --version >/dev/null 2>&1; then
		info "系统里没有 pip，自动安装"
		if command -v apt-get >/dev/null 2>&1; then
			DEBIAN_FRONTEND=noninteractive apt-get update -qq >/dev/null 2>&1
			# venv 一起装上：新版 Debian/Ubuntu 禁止往系统 Python 里 pip install，
			# 建虚拟环境是最干净的绕法
			DEBIAN_FRONTEND=noninteractive apt-get install -y -qq python3-pip python3-venv >/dev/null 2>&1
		elif command -v dnf >/dev/null 2>&1; then
			dnf install -y -q python3-pip >/dev/null 2>&1
		elif command -v yum >/dev/null 2>&1; then
			yum install -y -q python3-pip >/dev/null 2>&1
		elif command -v apk >/dev/null 2>&1; then
			apk add --quiet py3-pip >/dev/null 2>&1
		fi
	fi
	# 包管理器没搞定就试 Python 自带的引导
	"$PY" -m pip --version >/dev/null 2>&1 || "$PY" -m ensurepip --default-pip >/dev/null 2>&1
	# 还不行就用官方引导脚本
	if ! "$PY" -m pip --version >/dev/null 2>&1; then
		warn "包管理器装不上 pip，改用官方引导脚本"
		if [ "$DL" = curl ]; then
			curl -fsSL -o /tmp/get-pip.py https://bootstrap.pypa.io/get-pip.py 2>/dev/null
		else
			wget -qO /tmp/get-pip.py https://bootstrap.pypa.io/get-pip.py 2>/dev/null
		fi
		[ -s /tmp/get-pip.py ] && "$PY" /tmp/get-pip.py >/dev/null 2>&1
		rm -f /tmp/get-pip.py
	fi
	"$PY" -m pip --version >/dev/null 2>&1 || die "pip 装不上。手动装一下再重跑：apt install -y python3-pip python3-venv"

	# ---- 2. 优先用虚拟环境 ----
	# Debian 12 / Ubuntu 24 起，往系统 Python 里 pip install 会被 PEP 668 直接拒绝，
	# 而且装进系统目录容易和 apt 打架。有 venv 就用 venv，没有再退回系统安装。
	PYBIN="$PY"
	if [ ! -x "$DIR/venv/bin/python" ]; then
		if "$PY" -m venv "$DIR/venv" >/dev/null 2>&1; then
			info "已建虚拟环境 $DIR/venv"
		else
			warn "建不了虚拟环境（缺 python3-venv），改成装进系统 Python"
			rm -rf "$DIR/venv"
		fi
	fi
	[ -x "$DIR/venv/bin/python" ] && PYBIN="$DIR/venv/bin/python"

	# ---- 3. 装依赖，官方源不通换清华源 ----
	info "安装 onnxruntime / pillow / numpy（几十 MB，稍等）"
	PIPFLAG=""
	# 系统 Python 且被 PEP 668 管着，只能加这个参数
	if [ "$PYBIN" = "$PY" ] && "$PY" -m pip install --help 2>/dev/null | grep -q break-system-packages; then
		PIPFLAG="--break-system-packages"
	fi
	if "$PYBIN" -m pip install -q $PIPFLAG -r "$DIR/requirements.txt" 2>/dev/null; then
		PIP_OK=1
	else
		warn "官方源装不上，换清华源重试"
		"$PYBIN" -m pip install -q $PIPFLAG -i https://pypi.tuna.tsinghua.edu.cn/simple -r "$DIR/requirements.txt" && PIP_OK=1
	fi
	[ "$PIP_OK" = 1 ] || die "依赖装不上，手动试试：$PYBIN -m pip install $PIPFLAG onnxruntime pillow numpy"
	"$PYBIN" -c 'import onnxruntime, PIL, numpy' 2>/dev/null || die "依赖装完还是导入失败，检查 python3 环境"
fi

# ---------------------------------------------------------------- 下模型
# $1=目标文件 $2=huggingface 路径 $3=说明
fetch(){
	local out="$DIR/models/$1" url="https://$MIRROR/$2" label="$3"
	if [ -s "$out" ]; then
		info "$label 已存在，跳过（要重下就先删掉 $out）"
		return
	fi
	info "下载 $label …"
	if [ "$DL" = curl ]; then
		run "curl -fL --retry 3 -C - -o '$out.part' '$url'"
	else
		run "wget -c -O '$out.part' '$url'"
	fi
	run "mv '$out.part' '$out'"
}

if [ "$MODELS" = both ] || [ "$MODELS" = anime ]; then
	fetch anime.onnx        "SmilingWolf/wd-swinv2-tagger-v3/resolve/main/model.onnx"        "二次元模型 (446MB)"
	fetch selected_tags.csv "SmilingWolf/wd-swinv2-tagger-v3/resolve/main/selected_tags.csv" "二次元模型的标签表"
fi
if [ "$MODELS" = both ] || [ "$MODELS" = photo ]; then
	if [ "$PHOTO_SIZE" = quant ]; then
		fetch photo.onnx "AdamCodd/vit-base-nsfw-detector/resolve/main/onnx/model_quantized.onnx" "真人模型 量化版 (84MB)"
	else
		fetch photo.onnx "AdamCodd/vit-base-nsfw-detector/resolve/main/onnx/model.onnx" "真人模型 完整版 (329MB)"
	fi
fi

# ---------------------------------------------------------------- 生成配置
case "$MODELS" in
	both)  ANIME_ON=true;  PHOTO_ON=true;;
	anime) ANIME_ON=true;  PHOTO_ON=false;;
	photo) ANIME_ON=false; PHOTO_ON=true;;
esac

if [ -f "$DIR/config.json" ]; then
	warn "$DIR/config.json 已存在，保留不覆盖（想重新生成就先删掉它）"
else
	info "生成 config.json"
	if [ "$DRY" = 1 ]; then
		echo "${DIM}    \$ 写入 $DIR/config.json（anime=$ANIME_ON photo=$PHOTO_ON threads=$THREADS port=$PORT）${OFF}"
	else
		cat > "$DIR/config.json" <<EOF
{
  "host": "127.0.0.1",
  "port": $PORT,
  "token": "",

  "block_threshold": 0.85,
  "review_threshold": 0.60,
  "max_pixels": 40000000,
  "max_fetch_bytes": 33554432,
  "fetch_timeout": 10,

  "models": [
    {
      "name": "anime",
      "kind": "anime",
      "enabled": $ANIME_ON,
      "path": "$DIR/models/anime.onnx",
      "labels": "$DIR/models/selected_tags.csv",
      "channel_order": "bgr",
      "scale": 1.0,
      "mean": [0, 0, 0],
      "std": [1, 1, 1],
      "pad_color": [255, 255, 255],
      "activation": "sigmoid",
      "layout": "nhwc",
      "size": 448,
      "threads": $THREADS,
      "rating_weights": {
        "explicit": 1.0,
        "questionable": 0.5,
        "sensitive": 0.0,
        "general": 0.0
      }
    },
    {
      "name": "photo",
      "kind": "photo",
      "enabled": $PHOTO_ON,
      "path": "$DIR/models/photo.onnx",
      "channel_order": "rgb",
      "scale": 0.00392156862745098,
      "mean": [0.5, 0.5, 0.5],
      "std": [0.5, 0.5, 0.5],
      "pad_color": [255, 255, 255],
      "activation": "softmax",
      "layout": "nchw",
      "size": 384,
      "threads": $THREADS,
      "nsfw_index": 1
    }
  ]
}
EOF
	fi
fi

# config.json 里可能有 token，别让同机器其它用户读到
run "chown -R '$RUN_USER' '$DIR'"
run "chmod 600 '$DIR/config.json'"

# ---------------------------------------------------------------- 开机自启
info "注册 systemd 服务并设为开机自启"
if [ "$DRY" = 1 ]; then
	echo "${DIM}    \$ 写入 /etc/systemd/system/$SERVICE.service（User=$RUN_USER）${OFF}"
	echo "${DIM}    \$ systemctl enable --now $SERVICE${OFF}"
else
	command -v systemctl >/dev/null 2>&1 || die "这台机器没有 systemd，请参考 README 手动用 supervisor 或 nohup 托管"
	cat > "/etc/systemd/system/$SERVICE.service" <<EOF
[Unit]
Description=pan self-hosted image moderation
After=network.target

[Service]
Type=simple
User=$RUN_USER
WorkingDirectory=$DIR
ExecStart=$PYBIN $DIR/server.py $DIR/config.json
Restart=always
RestartSec=5
# 模型加载要读几百 MB 的文件，别限得太死
MemoryMax=3G

[Install]
WantedBy=multi-user.target
EOF
	systemctl daemon-reload
	systemctl enable "$SERVICE" >/dev/null 2>&1
	systemctl restart "$SERVICE"
fi

# ---------------------------------------------------------------- 自检
echo
if [ "$DRY" = 1 ]; then
	info "演练结束，什么都没改"
	exit 0
fi

info "等服务把模型加载起来（大模型要十几秒）"
OK=0
for i in $(seq 1 30); do
	sleep 2
	if curl -sSf --max-time 5 "http://127.0.0.1:$PORT/health" >/dev/null 2>&1; then OK=1; break; fi
done

if [ "$OK" != 1 ]; then
	echo
	die "服务没起来，看日志：journalctl -u $SERVICE -n 50 --no-pager"
fi

echo
curl -sS "http://127.0.0.1:$PORT/health"
echo
echo
info "装好了，已设为开机自启。"
echo
echo "  接下来去后台：系统设置 → 图片检测设置"
echo "    1. 「图片违规检测」选 ${GREEN}自建检测服务（本机模型）${OFF}"
echo "    2. 「检测服务地址」填 ${GREEN}http://127.0.0.1:$PORT/check${OFF}"
echo "    3. 保存后传张图试试"
echo
echo "  ${YELLOW}强烈建议先定阈值${OFF}，别直接用默认的 0.85 / 0.6："
echo "    $PYBIN $DIR/server.py $DIR/config.json --scan /你的网站目录/file/"
echo "  看正常图和违规图各自落在什么分数区间，把后台两个阈值卡中间。"
echo
echo "  常用命令："
echo "    systemctl status $SERVICE          # 看状态"
echo "    systemctl restart $SERVICE         # 改完配置重启"
echo "    journalctl -u $SERVICE -f          # 看日志"
echo "    bash install.sh --uninstall        # 卸载"
