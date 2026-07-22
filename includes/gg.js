// 创建并插入HTML结构
document.write(`
  <div style="width: 100%; overflow: hidden; white-space: nowrap; border: 1px solid #ccc; background: #f9f9f9; position: relative;">
    <div id="scrollText" style="display: inline-block; white-space: nowrap; animation: scroll 60s linear infinite; will-change: transform; font-size: 1.2em;">
      <!-- 文字内容将由 JavaScript 动态插入 -->
    </div>
  </div>
`);

// 创建并插入CSS样式
const style = document.createElement('style');
style.innerHTML = `
  @keyframes scroll {
    from {
      transform: translateX(100vw); /* 从视口外右侧开始 */
    }
    to {
      transform: translateX(-100%); /* 滚动到内容外左侧 */
    }
  }

  @keyframes colorChange {
    0% {
      color: red;
    }
    25% {
      color: orange;
    }
    50% {
      color: yellow;
    }
    75% {
      color: green;
    }
    100% {
      color: blue;
    }
  }

  @media (min-width: 768px) {
    div[style*="animation: scroll"] {
      animation-duration: 60s;
    }
  }
`;

// 将样式插入到<head>标签中
document.head.appendChild(style);

// 文字内容（包括A标签）
const text = "网站问题可联系QQ：<a href='https://wpa.qq.com/msgrd?v=3&uin=7619897&site=qq&menu=yes&jumpflag=1' target='_blank'>7619897</a>，网站内软件等问题勿扰！QQ交流群：<a href='https://qm.qq.com/q/Wddyy2mcGS' target='_blank'>251912122</a>。网络并非法外之地！请勿上传儿童色情内容或威胁、骚扰、诽谤、侵权、政治或鼓励非法行为等材料！上传者将屏蔽IP！";

// 获取目标元素
const scrollTextElement = document.getElementById('scrollText');

// 将文字内容拆分为字符并包装成span元素，同时保留a标签
function createSpans(text) {
  const container = document.createElement('span');
  container.innerHTML = text;  // 使用 innerHTML 来处理包含 HTML 标签的内容
  const spans = [];
  const children = container.childNodes;

  children.forEach((child, index) => {
    if (child.nodeType === 3) {  // 如果是文本节点
      const textContent = child.textContent;
      for (let i = 0; i < textContent.length; i++) {
        const span = document.createElement('span');
        span.textContent = textContent[i];
        span.style.animation = `colorChange 3s linear infinite ${index * 0.1 + i * 0.1}s`;
        spans.push(span);
      }
    } else if (child.nodeType === 1 && child.tagName === 'A') {  // 如果是a标签
      const a = document.createElement('a');
      a.href = child.href;
      a.target = child.target;
      a.textContent = child.textContent;
      spans.push(a);
    }
  });

  return spans;
}

// 动态生成文字并添加到页面
const spans = createSpans(text);
spans.forEach(span => scrollTextElement.appendChild(span));


















// 创建并添加 CSS 样式
const customStyle = document.createElement('style'); // 将 style 改为 customStyle
customStyle.innerHTML = `
    /* 给广告容器添加网格布局样式 */
    .txtguanggao {
        display: grid;
        grid-template-columns: repeat(4, 1fr); /* 4列 */
        grid-template-rows: repeat(5, auto);   /* 5行 */
        gap: 10px;  /* 设置单元格之间的间距 */
        padding: 10px;
        background: #f9f9f9;
        border: 1px solid #ccc;
    }

    /* 单个广告链接的样式 */
    .txtguanggao .dh {
        display: block;
        padding: 15px;
        text-align: center;
        color: #000;
        text-decoration: none;
        border: 1px solid #ccc;
        border-radius: 5px;
        transition: background-color 0.3s ease;
        position: relative;
    }

    /* 在悬浮时显示提示文字 */
    .txtguanggao .dh:hover::after {
        content: attr(data-tooltip); /* 使用 data-tooltip 属性来显示悬浮文字 */
        position: absolute;
        top: -30px;
        left: 50%;
        transform: translateX(-50%);
        background-color: rgba(0, 0, 0, 0.7);
        color: #fff;
        padding: 8px 12px;
        border-radius: 5px;
        font-size: 12px;
        white-space: normal; /* 允许换行 */
        word-wrap: break-word; /* 自动换行 */
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2); /* 给 tooltip 增加阴影效果 */
        z-index: 10;
        max-width: 200px; /* 限制 tooltip 的最大宽度 */
    }

    /* 针对移动端设备的媒体查询 */
    @media (max-width: 768px) {
        .txtguanggao .dh:hover::after {
            max-width: 150px; /* 调整最大宽度，适应小屏幕 */
            font-size: 10px; /* 缩小字体 */
            padding: 5px 0px; /* 缩小内边距 */
        }
    }

    /* Navbar 类别，增加样式 */
    .navbar{
        margin-bottom: 0px;
    }
`;

// 将样式加入页面
document.head.appendChild(customStyle);

// 创建主广告容器
const txtGuanggao = document.createElement('div');
txtGuanggao.className = 'txtguanggao';  // 为容器添加 class 名称

// 广告链接数据，增加悬浮显示的文字
const adLinks = [
    { href: "#", text: "广告招租", bgColor: "#007bff", tooltip: "点击查看广告详情。更多内容请咨询客服，我们提供最佳的广告位出租服务。" },
    { href: "#", text: "广告招租", bgColor: "#dc3545", tooltip: "抢占广告位，提升曝光。广告展示效果明显，效果保证，欢迎咨询。" },
    { href: "#", text: "广告招租", bgColor: "#28a745", tooltip: "开启您的广告之旅，增加品牌曝光，吸引更多客户。" },
    { href: "#", text: "广告招租", bgColor: "#ffc107", tooltip: "提升品牌知名度，接触更多用户。" },
    { href: "#", text: "广告招租", bgColor: "#ffc107", tooltip: "让更多人看到你，增加用户点击。" },
    { href: "#", text: "广告招租", bgColor: "#28a745", tooltip: "抓住商机，马上行动，广告位有限，广告效果有保证。" },
    { href: "#", text: "广告招租", bgColor: "#dc3545", tooltip: "超高转化率，点击了解详情，助力你的品牌增长。" },
    { href: "#", text: "广告招租", bgColor: "#007bff", tooltip: "广告位，等你来拿，详情咨询客服，提供多种广告位。" },
];

// 遍历每个广告链接并动态生成
adLinks.forEach((link) => {
    const a = document.createElement('a');
    a.href = link.href;
    a.target = '_blank';
    a.rel = 'nofollow';
    a.classList.add('dh');
    a.textContent = link.text;
    a.style.backgroundColor = link.bgColor;  // 设置每个广告链接的背景颜色
    a.setAttribute('data-tooltip', link.tooltip);  // 添加悬浮显示的文字

    // 将链接添加到广告容器中
    txtGuanggao.appendChild(a);
});

// 获取 .navbar.navbar-default 元素
const navbar = document.querySelector('.navbar.navbar-default');

// 如果元素存在，将广告容器添加到 navbar 下
if (navbar) {
    navbar.appendChild(txtGuanggao);
} else {
    console.error('没有找到具有 class "navbar navbar-default" 的元素');
}
