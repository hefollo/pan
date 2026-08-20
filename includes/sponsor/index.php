<?php
$nosession = true;
include __DIR__.'/../common.php';

$sponsors = $DB->getAll("SELECT `name`,`platform`,`amount`,`sponsor_time` FROM pre_sponsor ORDER BY id ASC");
if(!$sponsors) $sponsors = [];
$sponsors = array_map(function($row){
	return ['name'=>$row['name'], 'amount'=>$row['platform'].'赞助'.$row['amount'], 'time'=>$row['sponsor_time']];
}, $sponsors);

//收款码在后台“赞助名单管理”里配置；从未配置过就用目录里自带的图，配置成空则隐藏这一种收款方式
$pay_methods = [
	['key'=>'sponsor_img_weixin', 'default'=>'images/weixin.png', 'label'=>'微信赞助'],
	['key'=>'sponsor_img_qq',     'default'=>'images/qq.png',     'label'=>'QQ钱包赞助'],
	['key'=>'sponsor_img_alipay', 'default'=>'images/zhifubao.png','label'=>'支付宝赞助'],
];
$pay_list = [];
foreach($pay_methods as $m){
	$url = isset($conf[$m['key']]) ? trim($conf[$m['key']]) : $m['default'];
	if($url === '')continue;
	$pay_list[] = ['url'=>$url, 'label'=>$m['label']];
}
//赞助页原来写死蓝白配色，这里跟着站点外观走；未知主题回落到默认蓝白
$sp_themes = [
	'cloud'    => ['bg'=>'linear-gradient(135deg,#f8fafc 0%,#e8f0fe 100%)','surface'=>'#ffffff','line'=>'rgba(47,134,255,.10)','text'=>'#333333','soft'=>'#555555','muted'=>'#666666','faint'=>'#888888','primary'=>'#3a7bd5','primary_dark'=>'#2a5ba0','on_primary'=>'#ffffff','amount'=>'#09b83e'],
	'night'    => ['bg'=>'linear-gradient(135deg,#070d16 0%,#101824 100%)','surface'=>'#101824','line'=>'rgba(255,255,255,.10)','text'=>'#dbe8ff','soft'=>'#c2d2ea','muted'=>'#93a6c4','faint'=>'#7286a4','primary'=>'#2f86ff','primary_dark'=>'#1f5fc0','on_primary'=>'#ffffff','amount'=>'#37d67a'],
	'neon'     => ['bg'=>'linear-gradient(135deg,#050917 0%,#101d35 100%)','surface'=>'#0b1427','line'=>'rgba(86,130,218,.28)','text'=>'#cad8f0','soft'=>'#aebfdd','muted'=>'#8fa3c6','faint'=>'#7488ab','primary'=>'#73c7ff','primary_dark'=>'#7b3dff','on_primary'=>'#06101f','amount'=>'#24d7ff'],
	'aurora'   => ['bg'=>'linear-gradient(135deg,#183d86 0%,#561a82 100%)','surface'=>'rgba(255,255,255,.11)','line'=>'rgba(255,255,255,.2)','text'=>'#eef5ff','soft'=>'#dbe8ff','muted'=>'#c6d4f2','faint'=>'#a9bce4','primary'=>'#67e8ff','primary_dark'=>'#b872ff','on_primary'=>'#14224d','amount'=>'#7dffb0'],
	'onefour'  => ['bg'=>'linear-gradient(135deg,#040404 0%,#0d0d11 100%)','surface'=>'#09090c','line'=>'rgba(255,255,255,.1)','text'=>'#e7e8ec','soft'=>'#d0d2d9','muted'=>'#9c9faa','faint'=>'#7f848f','primary'=>'#ffffff','primary_dark'=>'#c8cad1','on_primary'=>'#050505','amount'=>'#8bd09f'],
	'celadon'  => ['bg'=>'linear-gradient(135deg,#f7fbfa 0%,#eef6f5 100%)','surface'=>'#ffffff','line'=>'rgba(24,120,120,.14)','text'=>'#123234','soft'=>'#1d474a','muted'=>'#5c8386','faint'=>'#8aa9ab','primary'=>'#2b9c9c','primary_dark'=>'#17696b','on_primary'=>'#ffffff','amount'=>'#12a05f'],
	'lilac'    => ['bg'=>'linear-gradient(135deg,#faf9fe 0%,#f4f3fb 100%)','surface'=>'#ffffff','line'=>'rgba(96,82,190,.14)','text'=>'#211c3d','soft'=>'#2f2952','muted'=>'#6f6890','faint'=>'#9a94b5','primary'=>'#6d5dd3','primary_dark'=>'#453a94','on_primary'=>'#ffffff','amount'=>'#12a05f'],
	'paper'    => ['bg'=>'linear-gradient(135deg,#fdfcfa 0%,#faf9f6 100%)','surface'=>'#ffffff','line'=>'rgba(120,114,98,.16)','text'=>'#1c1b18','soft'=>'#2c2b27','muted'=>'#6f6c62','faint'=>'#9a9788','primary'=>'#3f3f3d','primary_dark'=>'#6b6760','on_primary'=>'#ffffff','amount'=>'#2f7d4f'],
	'blush'    => ['bg'=>'linear-gradient(135deg,#fffafb 0%,#fdf4f6 100%)','surface'=>'#ffffff','line'=>'rgba(190,110,135,.16)','text'=>'#3a1c25','soft'=>'#4a2833','muted'=>'#8d6470','faint'=>'#b8949d','primary'=>'#e0648a','primary_dark'=>'#a13c5d','on_primary'=>'#ffffff','amount'=>'#12a05f'],
	'sky'      => ['bg'=>'linear-gradient(135deg,#f8fcff 0%,#eff8fd 100%)','surface'=>'#ffffff','line'=>'rgba(14,120,180,.14)','text'=>'#0b2637','soft'=>'#143a52','muted'=>'#557d97','faint'=>'#8aabc0','primary'=>'#0ea5e9','primary_dark'=>'#075e86','on_primary'=>'#ffffff','amount'=>'#12a05f'],
	'mint'     => ['bg'=>'linear-gradient(135deg,#f8fdfa 0%,#f0faf4 100%)','surface'=>'#ffffff','line'=>'rgba(30,150,100,.14)','text'=>'#0e2c1b','soft'=>'#16412a','muted'=>'#547f66','faint'=>'#88ad95','primary'=>'#22b573','primary_dark'=>'#106b41','on_primary'=>'#ffffff','amount'=>'#12a05f'],
	''sunset'  => ['bg'=>'radial-gradient(circle at 16% 10%,rgba(255,176,87,.3),transparent 36%),linear-gradient(135deg,#5a1030 0,#c2410c 100%)','surface'=>'rgba(255,255,255,.13)','line'=>'rgba(255,255,255,.24)','text'=>'#fff3ea','soft'=>'#ffe7d6','muted'=>'#e8bfae','faint'=>'#cfa392','primary'=>'#ffb057','primary_dark'=>'#ff6b9d','on_primary'=>'#3d0f2e','amount'=>'#6ee7b7'],
	''abyss'   => ['bg'=>'radial-gradient(circle at 16% 10%,rgba(56,224,216,.26),transparent 36%),linear-gradient(135deg,#062a3d 0,#0a5f63 100%)','surface'=>'rgba(255,255,255,.12)','line'=>'rgba(255,255,255,.22)','text'=>'#e8fbff','soft'=>'#d3f4fb','muted'=>'#96c6d2','faint'=>'#7ba9b6','primary'=>'#38e0d8','primary_dark'=>'#7dd3fc','on_primary'=>'#062434','amount'=>'#6ee7b7'],
	''emerald' => ['bg'=>'radial-gradient(circle at 16% 10%,rgba(74,222,128,.26),transparent 36%),linear-gradient(135deg,#07361f 0,#0f6b57 100%)','surface'=>'rgba(255,255,255,.12)','line'=>'rgba(255,255,255,.22)','text'=>'#eafff3','soft'=>'#d6fbe6','muted'=>'#93c9ac','faint'=>'#7aae92','primary'=>'#4ade80','primary_dark'=>'#5eead4','on_primary'=>'#07301f','amount'=>'#86efac'],
	''sakura'  => ['bg'=>'radial-gradient(circle at 16% 10%,rgba(224,100,138,.18),transparent 36%),linear-gradient(135deg,#ffe4ef 0,#dff1fe 100%)','surface'=>'rgba(255,255,255,.72)','line'=>'rgba(190,120,160,.22)','text'=>'#3d2030','soft'=>'#4a2537','muted'=>'#8a6478','faint'=>'#a98c9c','primary'=>'#e0648a','primary_dark'=>'#8b5cf6','on_primary'=>'#ffffff','amount'=>'#0f9d58'],
];
$sp_key = isset($conf['site_theme']) ? $conf['site_theme'] : 'cloud';
if(!isset($sp_themes[$sp_key])) $sp_key = 'cloud';
$sp = $sp_themes[$sp_key];
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>赞助支持 - 惜染外链网盘</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root{
            --sp-bg: <?php echo $sp['bg']?>;
            --sp-surface: <?php echo $sp['surface']?>;
            --sp-line: <?php echo $sp['line']?>;
            --sp-text: <?php echo $sp['text']?>;
            --sp-soft: <?php echo $sp['soft']?>;
            --sp-muted: <?php echo $sp['muted']?>;
            --sp-faint: <?php echo $sp['faint']?>;
            --sp-primary: <?php echo $sp['primary']?>;
            --sp-primary-dark: <?php echo $sp['primary_dark']?>;
            --sp-on-primary: <?php echo $sp['on_primary']?>;
            --sp-amount: <?php echo $sp['amount']?>;
        }
        /* 基础样式重置 */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'PingFang SC', 'Microsoft YaHei', sans-serif;
            background: var(--sp-bg);
            color: var(--sp-text);
            line-height: 1.5;
            padding: 15px;
            min-height: 100vh;
        }

        /* 主容器 */
        .container {
            max-width: 800px;
            margin: 0 auto;
        }

        /* 页面标题 */
        .page-header {
            text-align: center;
            margin-bottom: 25px;
            padding: 15px;
            background: var(--sp-surface);
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
        }

        .page-header h1 {
            font-size: 1.8rem;
            color: var(--sp-primary);
            margin-bottom: 8px;
        }

        .page-header p {
            color: var(--sp-muted);
            font-size: 0.95rem;
        }

        /* 超紧凑赞助卡片 */
        .mini-card {
            background: var(--sp-surface);
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            margin-bottom: 25px;
            border: 1px solid var(--sp-line);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .mini-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.12);
        }

        .mini-header {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
        }

        .mini-icon {
            font-size: 1.2rem;
            color: var(--sp-primary);
            background: rgba(0, 0, 0, 0.04);
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 12px;
        }

        .mini-title {
            font-size: 1.3rem;
            color: var(--sp-primary);
            font-weight: 600;
        }

        /* 紧凑列表 */
        .mini-list {
            list-style: none;
        }

        .mini-item {
            display: flex;
            align-items: center;
            margin-bottom: 12px;
            font-size: 0.9rem;
            padding: 10px 12px;
            border-radius: 8px;
            background: rgba(0, 0, 0, 0.03);
            transition: background 0.3s ease;
        }

        .mini-item:hover {
            background: rgba(0, 0, 0, 0.03);
        }

        .mini-item i {
            color: var(--sp-primary);
            margin-right: 10px;
            font-size: 1rem;
            min-width: 20px;
            text-align: center;
        }

        /* 支付方式 */
        .payment-methods {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin: 25px 0;
            flex-wrap: wrap;
        }

        .payment-card {
            text-align: center;
            transition: transform 0.3s ease;
        }

        .payment-card:hover {
            transform: scale(1.05);
        }

        .payment-card img {
            width: 130px;
            height: 130px;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            border: 1px solid var(--sp-line);
            transition: box-shadow 0.3s ease;
        }

        .payment-card img:hover {
            box-shadow: 0 6px 18px rgba(0, 0, 0, 0.15);
        }

        .payment-card p {
            margin-top: 10px;
            font-size: 0.9rem;
            color: var(--sp-soft);
            font-weight: 500;
        }

        /* 赞助名单 */
        .sponsor-list {
            background: var(--sp-surface);
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            overflow: hidden;
            margin-bottom: 25px;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .sponsor-list:hover {
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.12);
        }

        .list-header {
            background: linear-gradient(135deg, var(--sp-primary) 0%, var(--sp-primary-dark) 100%);
            color: var(--sp-on-primary);
            padding: 12px 15px;
            text-align: center;
            font-size: 1.1rem;
            font-weight: 500;
        }

        .column-titles {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            background: rgba(0, 0, 0, 0.04);
            padding: 10px 15px;
            font-size: 0.9rem;
            color: var(--sp-primary);
            border-bottom: 1px solid var(--sp-line);
            text-align: center;
            font-weight: 500;
        }

        .scrolling-list {
            height: auto;
            max-height: 280px;
            overflow: hidden;
            position: relative;
        }

        .scrolling-list ul {
            list-style: none;
        }

        .scrolling-list li {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            padding: 10px 15px;
            border-bottom: 1px solid var(--sp-line);
            font-size: 0.9rem;
            text-align: center;
            transition: background 0.3s ease;
        }

        .scrolling-list li:hover {
            background: rgba(0, 0, 0, 0.03);
        }

        .amount {
            color: var(--sp-amount);
            font-weight: 500;
        }

        .time {
            color: var(--sp-muted);
        }

        .no-data {
            text-align: center;
            padding: 30px;
            color: var(--sp-faint);
            font-style: italic;
        }

        /* 页脚 */
        .footer {
            text-align: center;
            padding: 20px;
            color: var(--sp-muted);
            font-size: 0.9rem;
            margin-top: 20px;
        }

        .footer a {
            color: var(--sp-primary);
            text-decoration: none;
            transition: color 0.3s ease;
        }

        .footer a:hover {
            color: var(--sp-primary-dark);
            text-decoration: underline;
        }

        /* 响应式调整 */
        @media (max-width: 768px) {
            .payment-card img {
                width: 110px;
                height: 110px;
            }

            .column-titles, .scrolling-list li {
                grid-template-columns: repeat(3, 1fr);
            }
        }

        @media (max-width: 480px) {
            .container {
                padding: 0 10px;
            }

            .page-header h1 {
                font-size: 1.5rem;
            }

            .payment-card img {
                width: 90px;
                height: 90px;
            }

            .column-titles, .scrolling-list li {
                grid-template-columns: repeat(2, 1fr);
            }

            .time {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- 页面标题 -->
        <div class="page-header">
            <h1>赞助支持惜染外链网盘</h1>
            <p>您的支持是我维持下去的动力！服务器每月续费需要41美元</p>
        </div>

        <!-- 超紧凑赞助卡片 -->
        <div class="mini-card">
            <div class="mini-header">
                <i class="bi bi-heart-fill mini-icon"></i>
                <h3 class="mini-title">零钱赞助</h3>
            </div>

            <ul class="mini-list">
                <li class="mini-item">
                    <i class="bi bi-coin"></i>
                    <span>1元不嫌少，10元不嫌多</span>
                </li>
                <li class="mini-item">
                    <i class="bi bi-server"></i>
                    <span>用于服务器维护与升级</span>
                </li>
                <li class="mini-item">
                    <i class="bi bi-lightning-charge"></i>
                    <span>确保服务稳定</span>
                </li>
            </ul>
        </div>

        <!-- 支付方式 -->
        <div class="payment-methods">
            <?php foreach($pay_list as $m){ $label = htmlspecialchars($m['label'], ENT_QUOTES, 'UTF-8'); ?>
            <div class="payment-card">
                <img src="<?php echo htmlspecialchars($m['url'], ENT_QUOTES, 'UTF-8')?>" alt="<?php echo $label?>" title="<?php echo $label?>">
                <p><?php echo $label?></p>
            </div>
            <?php }?>
        </div>

        <!-- 赞助名单 -->
        <div class="sponsor-list">
            <div class="list-header">
                <i class="bi bi-trophy-fill"></i> 赞助名单
            </div>

            <div class="column-titles">
                <div>昵称</div>
                <div>赞助金额</div>
                <div>赞助时间</div>
            </div>

            <div class="scrolling-list" id="sponsorList">
                <ul id="sponsorItems"></ul>
            </div>
        </div>

        <!-- 赞助数据JSON（后台"系统设置 - 赞助名单管理"里维护，不用再手动改这个文件） -->
        <script id="sponsorData" type="application/json">
<?php echo json_encode(['sponsors'=>$sponsors], JSON_UNESCAPED_UNICODE); ?>
        </script>

        <!-- 页脚 -->
        <div class="footer">
            <p>&copy; 2025 惜染外链网盘 | <a href="" target="_blank">mpimg.cn</a></p>
        </div>
    </div>

    <script>
        // 禁止右键菜单
        document.addEventListener('contextmenu', function(e) {
            e.preventDefault();
            alert('感谢您支持惜染外链网盘！\n官方网站: mpimg.cn');
        });

        // 加载赞助数据
        function loadSponsorData() {
            const data = JSON.parse(document.getElementById('sponsorData').textContent);
            const sponsorItems = document.getElementById('sponsorItems');
            const sponsorList = document.getElementById('sponsorList');

            // 清空现有内容
            sponsorItems.innerHTML = '';

            // 检查是否有数据
            if (data.sponsors.length === 0) {
                const noData = document.createElement('div');
                noData.className = 'no-data';
                noData.textContent = '暂无赞助记录，成为第一个赞助者吧！';
                sponsorList.appendChild(noData);
                return;
            }

            // 添加数据项
            data.sponsors.forEach(sponsor => {
                const li = document.createElement('li');
                li.innerHTML = `
                    <div>${sponsor.name}</div>
                    <div class="amount">${sponsor.amount}</div>
                    <div class="time">${sponsor.time}</div>
                `;
                sponsorItems.appendChild(li);
            });

            // 只有当数据足够多时才启用滚动效果
            if (data.sponsors.length > 5) {
                // 克隆项目实现无缝滚动
                const items = sponsorItems.querySelectorAll('li');
                items.forEach(item => {
                    const clone = item.cloneNode(true);
                    sponsorItems.appendChild(clone);
                });

                // 设置滚动动画
                let position = 0;
                let scrollSpeed = 0.3;
                const scrollHeight = sponsorItems.scrollHeight / 2;

                function scroll() {
                    position += scrollSpeed;
                    if (position >= scrollHeight) {
                        position = 0;
                    }
                    sponsorItems.style.transform = `translateY(-${position}px)`;
                    requestAnimationFrame(scroll);
                }

                scroll();
            }
        }

        // 页面加载完成后执行
        window.addEventListener('load', loadSponsorData);
    </script>
</body>
</html>
