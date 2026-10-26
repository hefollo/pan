# 主题开发文档

这份文档写给想给网盘换外观的站长：怎么写一个主题包、怎么在后台摆页面上的卡片。

不需要会 PHP。主题包里只有 **theme.json、Mustache 模板、布局 JSON、样式表、脚本、图片和字体**，程序不接受任何能在服务器上执行的文件。

> 最快的上手方式：在后台「外观设置 → 主题包」里把一个现有外观**导出**，改样式表，打成 zip 再**上传**。下面第 1 节就是这条路。示例主题在 [`docs/theme-example/sample-note/`](theme-example/sample-note/)。

---

## 0. 三个概念

| 概念 | 是什么 | 谁来改 |
|---|---|---|
| **卡片**（block） | 页面上的一块内容：导航栏、文件列表、上传面板、广告位…… 每张卡片有一个模板和若干配置项 | 程序自带的卡片由程序提供；主题可以覆盖它们的模板，也可以自带纯展示卡片 |
| **主题包**（theme） | 一个文件夹：`theme.json` + 样式 + 模板 + 默认布局。可以继承一个已有外观，只改一部分 | 主题作者写，站长在后台上传 |
| **布局**（layout） | 某个页面上放哪些卡片、什么顺序、怎么嵌套、每张卡片的配置 | 主题给默认布局；站长在后台「页面布局」里拖拽修改，按主题、按页面分别保存 |

前台和后台都是这样拼出来的。后台页面里的表单和表格是程序的功能（字段名、按钮 id 脚本要用），主题只能**换皮、调整摆放**，不能改掉它们的结构。

---

## 1. 五分钟做一个主题

1. 后台 → 设置 → **外观设置**，页面下方「主题包」面板里，在「把一个外观导出成 zip，当作写新主题的起点」那一栏选一个你喜欢的外观，点**导出**。得到 `<外观id>-custom.zip`。
2. 解压，目录大概是：
   ```
   cloud-custom/
   ├─ theme.json            主题清单
   ├─ assets/style.css      你的样式（现在是空的）
   ├─ layouts/*.json        这个外观现在各页面的布局
   └─ README.md
   ```
3. 改 `theme.json` 里的 `id`（小写字母开头，字母数字短横线）和 `name`，在 `assets/style.css` 里写样式：
   ```css
   body.theme-cloud-custom .well{ border-radius: 20px; }
   ```
4. 把文件夹打成 zip（zip 里带不带这一层文件夹都行），回到「主题包」面板**上传**。
5. 在上面的外观列表里选中你的主题，**保存**。前台、后台一起换。

要改页面上卡片的排列，去 设置 → **页面布局**。

---

## 2. 主题包的目录

```
my-theme/
├─ theme.json                  必需
├─ assets/                     样式、脚本、图片、字体（目录名随意，theme.json 里引用就行）
│  ├─ style.css
│  └─ admin.css
├─ templates/                  Mustache 模板，覆盖程序的同名模板，或者给主题自带的卡片用
│  └─ blocks/
│     ├─ shell/footer.mustache        覆盖页脚卡片的模板
│     └─ theme/welcome.mustache       主题自带卡片 theme.welcome 的模板
├─ layouts/                    各页面的默认布局
│  ├─ index.json
│  └─ admin/index.json
├─ preview.png                 外观列表里的预览图（可选）
└─ README.md                   说明（可选）
```

上传后装在网站的 `content/themes/<id>/` 下。这个目录不在程序更新范围内，在线更新、覆盖上传更新包都不会动它。

### 包的限制

- 允许的扩展名：`json mustache css js png jpg jpeg gif webp svg ico woff woff2 ttf otf txt md`，**别的一律整包拒绝**（包括 `.php`、`.htaccess`、`.user.ini`）。
- 路径只能用字母、数字、`_ - .`，不能有 `..`、反斜杠、隐藏文件（`.` 开头）、绝对路径。有的打包工具会把路径写成反斜杠，被拒的话换 7-Zip 等工具重新打包。
- SVG 里不能有 `<script>`、`on…=` 事件属性、`javascript:`、`<foreignObject>`、`<iframe>` 等。
- 模板必须在 `templates/` 下，安装前会逐个解析，**有语法错误整包拒绝**并告诉你是哪个文件。
- `theme.json` 里引用的样式、脚本、预览图必须真的在包里；`extends` 的父主题必须存在。
- 大小：zip 不超过 20 MB，解压后不超过 30 MB，单个文件不超过 5 MB，最多 500 个文件。
- 主题 id 不能和内置外观重名。

### 升级、删除、导出

- **升级**：上传同一个 `id` 的新版本就是覆盖升级。站长调过的主题设置、改过的布局都保留。
- **删除**：在「主题包」面板里删。正在使用的主题、被别的主题继承的主题不能删。删除会同时删掉它的设置和布局。
- **导出**：上传的主题导出原包，并把站长在后台**已发布**的布局写进 `layouts/`，拿到别的站上传就是同一个样子。内置外观导出成「继承它的子主题」骨架。

> 主题包里的 JS 在你的网站域名下运行，能做的事和站长自己写在页脚里的代码一样多。**只装信任来源的主题。**

---

## 3. theme.json

```json
{
    "id": "sample-note",
    "name": "示例：便签风",
    "description": "一句话介绍",
    "version": "1.0.0",
    "author": "你的名字",
    "engine": 1,
    "group": "color",
    "extends": "cloud",
    "flags": [],
    "body_class": "",
    "styles": ["assets/style.css"],
    "scripts": [],
    "admin_styles": ["assets/admin.css"],
    "preview": "preview.svg",
    "sort": 1000,
    "settings": [ … ],
    "blocks": [ … ]
}
```

| 字段 | 必需 | 说明 |
|---|---|---|
| `id` | 是 | 小写字母开头，字母、数字、短横线，2～40 个字符。必须和目录名一致 |
| `name` | 是 | 外观列表里显示的名字，40 字以内 |
| `description` / `version` / `author` | | 显示用 |
| `engine` | | 外观引擎版本，目前只有 `1` |
| `group` | | `layout`（会改页面结构）或 `color`（只改配色），只影响后台外观列表的分组 |
| `extends` | | 继承的主题 id（内置外观或别的上传主题）。见第 4 节 |
| `flags` | | 主题标记，影响默认布局和外壳脚本，见第 4 节 |
| `body_class` | | 额外加在前台 `<body>` 上的类名 |
| `styles` | | 前台加载的样式表，按顺序 |
| `scripts` | | 前台加载的脚本，放在页面最后、jQuery 和 Bootstrap 之后 |
| `admin_styles` | | 后台加载的样式表 |
| `preview` | | 外观列表里的预览图 |
| `sort` | | 外观列表里的排序，小的在前 |
| `settings` | | 站长可调的设置，见第 5 节 |
| `blocks` | | 主题自带的卡片，见第 7 节 |

内置外观的 `theme.json` 在程序的 `themes/<id>/` 下，可以参考；内置外观的 `legacy` 字段对上传的主题无效。

---

## 4. 继承、类名和标记

**继承**：`"extends": "cloud"` 表示在「蓝白清爽」的基础上改。父主题的样式表、模板、默认布局、主题卡片都照样生效，你的主题只写不一样的部分。可以多层继承，连自己在内最多 4 层。

**类名**：前台 `<body>` 上同时有子主题和所有父主题的类，比如 `theme-sample-note theme-cloud`；后台 `<body>` 上是 `admin-theme-sample-note admin-theme-cloud`。样式表里用子主题的类开头，就只影响你的主题，也压得过父主题的同名规则：

```css
body.theme-sample-note .navbar{ background: #fff; }
body.admin-theme-sample-note.admin-body .panel{ border-radius: 14px; }
```

后台的选择器要写 `body.admin-theme-xxx.admin-body`（两个类），因为后台公共样式就是两个类的，少一个优先级不够。

**标记**（`flags`）：

| 标记 | 作用 |
|---|---|
| `layout` | 布局型外观（侧栏、顶栏这类结构变化），`<body>` 上加 `layout-theme` |
| `studio` | 工作台家族：用工作台风的外壳和首页默认布局（`_shell@studio`、`index@studio`），`<body>` 上加 `studio-layout`，页面末尾加工作台脚本 |

标记沿继承链合并，继承了工作台风的主题自动带 `studio`。

---

## 5. 主题设置（settings）

让站长不改代码就能调颜色、圆角、图片、文字。在后台「外观设置」里，选中你的主题后会多一个「主题设置」面板。

```json
"settings": [
    {"key": "primary", "type": "color", "label": "主色", "default": "#d97706", "help": "按钮、链接的颜色"},
    {"key": "radius", "type": "number", "label": "圆角", "default": 14, "unit": "px", "min": 0, "max": 30},
    {"key": "texture", "type": "image", "label": "背景纹理", "default": ""},
    {"key": "tagline", "type": "text", "label": "标语", "default": "你好"},
    {"key": "dense", "type": "switch", "label": "紧凑模式", "default": 0},
    {"key": "mode", "type": "select", "label": "风格", "default": "a", "options": {"a": "甲", "b": "乙"}}
]
```

| type | 说明 | 输出成 CSS 变量 |
|---|---|---|
| `color` | `#rrggbb` | `--theme-<key>: #rrggbb` |
| `number` | 数字，可带 `unit`（`px em rem % s ms deg`）、`min`、`max` | `--theme-<key>: 14px` |
| `image` | 图片地址（站内相对路径或 http/https） | `--theme-<key>: url("…")` |
| `text` / `textarea` | 文字 | 不输出变量，模板里用 |
| `html` | 原样输出的一段 HTML（只有管理员能改） | 不输出变量 |
| `lines` | 多行文字，每行一条 | 不输出变量 |
| `switch` | 开关，值是 1 / 0 | 不输出变量 |
| `select` | 下拉，`options` 是 值→名称 | 不输出变量 |

- `key` 小写字母开头，字母数字下划线；变量名里下划线换成短横线（`primary_dark` → `--theme-primary-dark`）。
- 变量挂在 `:root` 上，前台、后台、404 页都有。样式表里这样用：`border-radius: var(--theme-radius, 14px);`
- **`primary` 和 `primary_dark` 是特殊的**：程序会把它们接到全站的主色变量上（前台 `--primary` / `--primary-dark`，后台 `--admin-primary` / `--admin-primary-dark`），按钮、链接、高亮自动换色，不用自己写一遍。
- 模板里用 `{{theme.settings.<key>}}` 取值。
- 最多 60 项。

---

## 6. 模板

模板是 [Mustache](https://mustache.github.io/mustache.5.html)，一种「没有逻辑」的模板语言：只能取值、判断有没有、循环，不能调用函数、不能执行代码。所以主题里的模板再怎么写也碰不到服务器。

### 常用语法

```mustache
{{name}}                  取值，自动转义 HTML
{{{title_html}}}          原样输出（只用在以 _html 结尾的值上）
{{#show}}…{{/show}}       show 为真时输出；如果是数组，对每一项循环
{{^show}}…{{/show}}       show 为假或空数组时输出
{{cfg.title}}             点号取下一层
{{> blocks/core/html}}    引入另一个模板（partial）
{{! 注释 }}
```

**转义约定**：程序给模板的数据里，名字以 `_html` 结尾的是已经处理好的 HTML，用三个大括号 `{{{x_html}}}` 输出；其余一律用两个大括号，自动转义。不要把普通值放进三个大括号里。

### 每张卡片的模板都能拿到

| 变量 | 内容 |
|---|---|
| `cfg.<key>` | 这张卡片在「页面布局」里填的配置（按卡片的配置项清洗过） |
| `theme.id` / `theme.name` | 当前主题 |
| `theme.settings.<key>` | 主题设置 |
| `theme.is_<标记>` | 有这个标记时为真，比如 `{{#theme.is_studio}}` |
| `theme.is_<主题id>` | 当前主题 id（短横线换成下划线）为真，比如 `{{#theme.is_sample_note}}` |
| `site.title_html` | 网站标题（原样输出） |
| `slot_html` | 容器卡片里放的子卡片渲染结果（原样输出） |

程序自带卡片还有各自的数据（文件列表、权限、统计……），名字见对应的系统模板。

### 覆盖程序的模板

在主题的 `templates/` 下放一个和程序同名的模板，就替换掉它。程序的全部模板在 `includes/templates/` 下，**先把原文件复制过来再改**，保留它用到的变量和元素的 id / class（页面脚本靠它们工作）。

查找顺序：当前主题 → 父主题 → …… → 程序默认。

可以覆盖的模板（`includes/templates/` 下）：

- 外壳：`shell/head`（`<head>`）、`shell/tail`（页面末尾的基础脚本）、`shell/sysmsg`（系统提示页）
- 后台：`admin/head`、`admin/shell_script`、`admin/theme_cards`、`admin/theme_settings`、`admin/theme_packages`
- 分享页文档头尾：`share/document_open`、`share/document_close`
- 每张卡片：`blocks/<组>/<名>`，比如 `blocks/shell/footer`、`blocks/index/filelist`，清单见第 9 节
- 卡片的脚本片段：`blocks/*/…_scripts`、`blocks/user/uc_script` 等（一般不要改）

`shell/head`、`shell/tail`、`admin/head`、`admin/shell_script` 里有全站必需的样式和脚本，改错了整站会坏，没把握就别动。

### 模板出错会怎样

- 上传时语法错误会被拒绝。
- 运行时主题模板出错（比如引用了不存在的 partial），程序记一条错误、改用程序默认模板，页面不会白屏。主题自带卡片没有默认模板可退，就不输出。
- 最近的模板错误显示在「外观设置 → 主题包」面板最下面。

---

## 7. 主题自带的卡片（blocks）

主题可以带自己的卡片，用来放横幅、说明块、链接组这类**纯展示**内容。它们没有 PHP，模板只能用 `cfg`、`theme`、`site`、`slot_html`（以及下面的 `lines`）。

```json
"blocks": [
    {
        "id": "welcome",
        "name": "欢迎横幅",
        "description": "首页顶部的大标题和按钮",
        "scope": "front",
        "pages": ["index"],
        "container": false,
        "schema": [
            {"key": "title", "type": "text", "label": "标题", "default": "欢迎"},
            {"key": "show_btn", "type": "switch", "label": "显示按钮", "default": 1}
        ]
    }
]
```

| 字段 | 说明 |
|---|---|
| `id` | 小写字母开头，字母数字下划线。卡片的完整 id 是 `theme.<id>`，比如 `theme.welcome` |
| `name` / `description` | 编辑器里显示的名字和说明 |
| `scope` | `front`（前台，默认）、`admin`（后台）、`both` |
| `pages` | 能放在哪些页面，省略表示任意页面。页面名见第 8 节 |
| `container` | `true` 表示是容器，可以往里放别的卡片，子卡片渲染结果在 `slot_html` |
| `schema` | 配置项，格式和第 5 节的 settings 一样 |

模板放在 `templates/blocks/theme/<id>.mustache`：

```mustache
<section class="my-welcome">
  <h1>{{cfg.title}}</h1>
  {{#cfg.show_btn}}<a class="btn btn-primary" href="./upload.php">上传</a>{{/cfg.show_btn}}
</section>
```

**多行配置**：`lines` 类型的配置项，程序额外给一份拆好的 `lines.<key>`：每行一项，`text` 是整行，按 `|` 拆成 `c1`～`c6`：

```mustache
{{! 配置：每行「名称|链接」 }}
{{#lines.items}}<a href="{{c2}}">{{c1}}</a>{{/lines.items}}
```

子主题继承父主题的卡片；同 id 的以子主题为准。一个主题最多 50 张卡片。缺模板的卡片不输出，并记进模板错误日志。

---

## 8. 布局

布局是一棵卡片树，存成 JSON：

```json
{
    "nodes": [
        {"id": "notice", "block": "common.notice"},
        {"id": "welcome", "block": "theme.welcome", "config": {"title": "你好"}},
        {
            "id": "main", "block": "core.box", "config": {"class": "container"},
            "children": [
                {"id": "list", "block": "index.filelist", "config": {"cards": 1}},
                {"id": "old", "block": "index.stats", "hidden": 1}
            ]
        }
    ]
}
```

| 键 | 说明 |
|---|---|
| `id` | 节点 id，同一页面里唯一，字母数字下划线短横线。编辑器里预览定位用 |
| `block` | 卡片 id |
| `config` | 卡片配置，没写的用默认值；卡片配置项里没有的键会被丢掉 |
| `hidden` | `1` 表示先不显示（保留配置，随时能打开） |
| `children` | 子卡片，只有容器卡片会渲染 |

最多 8 层、400 个节点。放在页面上不适用的卡片（比如把购买页卡片放进首页）不输出。

### 页面名

| 页面名 | 页面 | 页面名 | 页面 |
|---|---|---|---|
| `_shell` | 前台公共外壳（导航、侧栏、页脚） | `admin/_shell` | 后台公共外壳（导航） |
| `index` | 首页 / 文件列表 | `admin/index` | 后台首页 |
| `upload` | 上传页 | `admin/file` | 文件管理 |
| `file` | 文件页 | `admin/folders` | 用户文件夹 |
| `user` | 个人中心 | `admin/replace` | 覆盖记录 |
| `buy` | 购买页 | `admin/green_log` | 内容检测记录 |
| `login` | 登录页 | `admin/user` | 用户管理 |
| `edit` | 在线编辑 | `admin/order` | 订单记录 |
| `sponsor` | 赞助名单 | `admin/mail_log` | 发信记录 |
| `violation` | 违规公示 | `admin/level` | 会员等级设置 |
| `share` | 文件夹分享 | `admin/set-<mod>` | 设置页（site appearance file api user green iptype account） |
| | | `admin/set_script` `admin/set_stor` `admin/set_mail` `admin/set_pay` `admin/set_violation` `admin/set_sponsor` | 其它设置页 |
| | | `admin/update` | 程序更新日志 |
| | | `admin/login` | 后台登录页 |
| `page-<地址名>` | 站长新建的自定义页面（`page.php?p=<地址名>`） | | |

外壳布局里的 `shell.page` 卡片是「页面内容放在这里」的占位，不能删。

### 自定义页面

站长可以在编辑器里新建页面（比如「首页」「关于我们」），访问地址 `page.php?p=<地址名>`，内容就是布局 `page-<地址名>`。

- **自定义页面是网站内容，所有外观共用一份**：换外观时页面内容不变，只是样子跟着外观变。所以页面里最好用跨外观通用的卡片（第 9 节的「通用展示」组），别用只适合某个外观家族的卡片（`studio.*`、`cockpit.*`、`index.portal_hero`）。
- 新建的页面在站长摆卡片之前用默认布局：主题（及父主题）的 `layouts/page.json`，没有就用程序的 `includes/layouts/page.json`（一张横幅）。主题想给新页面一个好看的起点，就带一份 `layouts/page.json`。
- **直接写代码**：新建页面时选「空白代码页」，页面就是一张「代码」卡片（`core.code`），里面分 HTML、CSS、JS 三个代码框（高亮、行号、自动补全闭合标签），改完右边实时预览。CSS 输出成一段 `<style>`，JS 放在页面最后执行（可以直接用 jQuery 的 `$`）。「代码」卡片也能和别的卡片混着放，放进任意前台页面。代码只有管理员能改，原样输出，写什么就执行什么。
- **显示网站外壳**（页面设置里，默认勾着）：取消勾选后，这个页面不再套导航、页脚和外观样式，整页只有页面自己的内容，只带最基本的 `<head>` 和 jQuery，适合整页自己写。
- 任意一个自定义页面都可以设为**网站入口**：不带参数打开网站根目录 `/` 时显示它；文件列表经菜单「文件列表」（`./?list=1`）和带参数的链接（搜索、筛选、翻页）照常访问。默认没有入口，`/` 就是文件列表。

### 导航菜单

前台导航栏（`shell.navbar`）的菜单项在编辑器里配置：选「前台公共外壳」，点「导航栏」卡片，配置里的「菜单项」是一个可拖动的列表——内置项（文件列表、上传、赞助、购买、违规公示）可以排序、隐藏、改名、换图标，还能加自定义页面和链接。**菜单是网站级的**，所有外观共用，存在站点设置里，不在主题的外壳布局里；草稿 / 发布和布局一样。主题的默认外壳布局里也可以给导航栏写 `menu` 配置，只在站长从没配置过菜单时生效。

### 用哪份布局

一个页面实际用的布局按这个顺序找，找到就停：

1. 站长在后台「页面布局」里**发布**过的（固定页面按主题分别保存；自定义页面全站一份）；
2. 主题（及父主题）的 `layouts/<页面名>.json`；
3. 程序按标记准备的默认布局 `includes/layouts/<页面名>@<标记>.json`；
4. 程序默认布局 `includes/layouts/<页面名>.json`。

所以主题里的 `layouts/` 是**默认值**：站长没改过时生效；站长改过后以站长的为准，在编辑器里点「恢复主题默认布局」回到主题的版本。

### 后台页面的布局

后台页面的内容是程序捕获好的一个个**面板**（`admin.panel` 卡片，配置 `panel` 是面板编号）。布局能决定面板的顺序、外面包什么容器、隐藏哪些面板，面板里面的表单不变。各页面有哪些面板，看 `includes/layouts/admin/<页面>.json`，或者在编辑器里打开那一页。

---

## 9. 程序自带的卡片

`必需` 的卡片页面靠它工作，编辑器里不能删也不能隐藏。`容器` 可以放子卡片。

#### 前台

| 卡片 id | 名称 | 能放的页面 | 类型 | 配置项 |
|---|---|---|---|---|
| `buy.current` | 当前等级 | buy | — | — |
| `buy.dialog` | 支付弹窗 | buy | 必需 | — |
| `buy.header` | 购买页标题 | buy | — | — |
| `buy.methods` | 支付方式 | buy | — | — |
| `buy.plans` | 套餐列表 | buy | — | — |
| `buy.tips` | 购买说明 | buy | — | — |
| `buy.upgrades` | 补差价升级 | buy | — | — |
| `cockpit.head` | 问候栏 | 任意 | — | — |
| `cockpit.panel_dist` | 存储分布 | 任意 | — | — |
| `cockpit.panel_feed` | 最近上传 | 任意 | — | `limit`（number） |
| `cockpit.panel_links` | 快捷入口 | 任意 | — | — |
| `cockpit.quota` | 额度卡 | 任意 | — | — |
| `common.ads_banner` | 广告位 | 任意 | — | — |
| `common.notice` | 站点公告 | 任意 | — | — |
| `common.permbar` | 权限条 | 任意 | — | `where`（select） |
| `common.reward` | 打赏码 | 任意 | — | `heading_bg`（text）、`qr_comment`（switch） |
| `common.subtitle` | 说明文字 | 任意 | — | `text`（text） |
| `edit.editor` | 在线编辑器 | edit | — | — |
| `file.preview` | 文件预览 | file | — | — |
| `file.tabs` | 外链与管理 | file | — | — |
| `file.tips` | 文件页提示 | 任意 | — | — |
| `index.crumb` | 面包屑 | index | — | — |
| `index.filelist` | 文件列表 | index | — | `action_col`（select）、`cards`（switch） |
| `index.filterbar` | 类型筛选 | index | — | `tools`（switch） |
| `index.mac_drop` | 拖拽上传入口 | index | — | — |
| `index.portal_hero` | 首屏上传区 | index | — | — |
| `index.preview` | 文件预览面板 | index | — | — |
| `index.stats` | 统计卡 | 任意 | — | `extra`（select） |
| `index.title` | 标题与搜索 | index | — | `cta`（text）、`mac_toggle`（switch） |
| `login.empty` | 无法登录提示 | login | — | — |
| `login.mail` | 邮箱登录与注册 | login | — | — |
| `login.oauth` | 快捷登录 | login | — | — |
| `login.title` | 登录标题 | login | — | — |
| `share.file` | 分享文件预览 | share | — | — |
| `share.heading` | 分享标题 | share | — | — |
| `share.listing` | 分享文件列表 | share | — | — |
| `share.message` | 分享提示与解锁 | share | — | — |
| `shell.ads` | 广告位脚本 | _shell | — | — |
| `shell.footer` | 页脚 | _shell | — | — |
| `shell.navbar` | 导航栏 | _shell | 容器 | `menu`（menu，网站级菜单，见第 8 节） |
| `shell.page` | 页面内容 | _shell | 必需 | — |
| `shell.side_perm` | 我的权限 | _shell | — | — |
| `shell.side_today` | 今日上传 | _shell | — | — |
| `shell.studio_topbar` | 顶部搜索条 | _shell | — | — |
| `shell.studio_upsell` | 升级卡 | _shell | — | — |
| `sponsor.intro` | 赞助页标题 | 任意 | — | `text`（text） |
| `sponsor.list` | 赞助记录 | 任意 | — | — |
| `sponsor.pay` | 赞助收款码 | 任意 | — | — |
| `studio.hero` | 主视觉横幅 | 任意 | — | `kicker`（html）、`title`（html）、`sub`（html）、`tags`（html）、`slogan`（html）、`checks`（lines）、`feats`（lines）、`cta`（switch）、`cta_text`（text）、`badge`（switch）、`search`（switch） |
| `studio.panel_acts` | 快捷操作面板 | 任意 | — | — |
| `studio.panel_drop` | 上传卡 | 任意 | — | — |
| `studio.panel_feed` | 最近动态面板 | 任意 | — | `limit`（number） |
| `studio.panel_rights` | 我的权限面板 | 任意 | — | `extra_rows`（lines）、`vip_btn`（switch） |
| `studio.panel_store` | 存储空间面板 | 任意 | — | — |
| `studio.panel_today` | 今日上传面板 | 任意 | — | — |
| `studio.promo` | 推广横幅 | 任意 | — | `title`（html）、`sub`（html）、`chips`（lines）、`slogan`（html）、`need_buy`（switch） |
| `upload.app` | 上传页挂载点 | upload | 容器 必需 | — |
| `upload.folder_target` | 上传目标文件夹 | upload | — | — |
| `upload.loading` | 加载动画 | upload | — | — |
| `upload.panel` | 上传面板 | upload | 容器 | — |
| `upload.tips` | 上传提示 | upload | — | — |
| `user.acc_avatar` | 头像设置 | user | — | — |
| `user.acc_binds` | 登录方式 | user | — | — |
| `user.acc_nickname` | 修改昵称 | user | — | — |
| `user.acc_password` | 密码 | user | — | — |
| `user.acc_profile` | 账号资料 | user | — | — |
| `user.api_about` | API 说明 | user | — | — |
| `user.api_create` | 创建 API 密钥 | user | — | — |
| `user.api_docs` | API 对接文档 | user | — | — |
| `user.api_keys` | API 密钥列表 | user | — | — |
| `user.api_secret` | 新密钥展示框 | user | — | — |
| `user.files_filters` | 文件类型筛选 | user | — | — |
| `user.files_hint` | 私密说明 | user | — | — |
| `user.files_list` | 我的文件列表 | user | — | — |
| `user.files_toolbar` | 文件工具条 | user | — | — |
| `user.head` | 个人中心头部 | user | — | — |
| `user.orders` | 订单记录 | user | — | — |
| `user.overview_cards` | 概览数字卡 | user | — | — |
| `user.overview_rights` | 权限与额度 | user | — | — |
| `user.overview_types` | 文件类型分布 | user | — | — |
| `user.tab` | 页签内容 | user | 容器 | `tab`（select） |
| `user.tabs` | 页签导航 | user | — | — |
| `violation.intro` | 公示页标题 | 任意 | — | — |
| `violation.list` | 违规公示列表 | 任意 | — | — |

**通用展示**（任意前台页面，跨 28 套外观都能用，颜色只取外观变量，适合首页和自定义页面）：

| 卡片 id | 名称 | 配置项 |
|---|---|---|
| `common.hero` | 横幅 | 标题、说明（多行）、主 / 次按钮文字和链接、配图、对齐（靠左 / 居中）、底色（面板色 / 主色 / 无） |
| `common.media` | 图文 | 图片、标题、说明、按钮、图片在左 / 右 |
| `common.buttons` | 按钮组 | 每行「文字\|链接\|primary」、对齐 |
| `common.features` | 特性网格 | 标题、每行「fa-图标\|标题\|说明」、每行几格（2 / 3 / 4） |

卡片里的链接只收 `http(s)://` 和站内相对地址，`javascript:` 之类会被丢掉。样式在 `assets/css/style.css` 的「通用展示卡片」一段（类名 `pc-*`），主题可以在自己的样式表里改。

「任意」指任意前台页面。卡片的数据在页面里才有意义（比如购买页卡片要放在购买页），放错地方的卡片可能什么都不输出。

#### 前后台通用

| 卡片 id | 名称 | 能放的页面 | 类型 | 配置项 |
|---|---|---|---|---|
| `core.box` | 容器 | 任意 | 容器 | `tag`（select）、`class`（text）、`dom_id`（text）、`style`（text） |
| `core.html` | 自定义 HTML | 任意 | — | `html`（textarea） |
| `core.code` | 代码（只能放前台页面） | 任意 | — | `html`、`css`、`js`（code，代码框） |

#### 后台

| 卡片 id | 名称 | 能放的页面 | 类型 | 配置项 |
|---|---|---|---|---|
| `admin.navbar` | 后台导航 | admin/_shell | — | — |
| `admin.panel` | 后台面板 | 任意后台页面 | — | `panel`（text） |

每张卡片的定义在 `includes/blocks/<组>/<名>.php`，模板在 `includes/templates/blocks/<组>/<名>.mustache`。

---

## 10. 后台「页面布局」编辑器

设置 → **页面布局**。

1. 上面选**主题**和**页面**。可以编辑没在使用的主题，换外观之前先把布局摆好。
2. 左边是卡片树：拖 <kbd>☰</kbd> 调整顺序，能拖进带文件夹图标的容器；眼睛图标隐藏 / 显示，还有复制、删除。
3. 点卡片名，在下面改它的配置。
4. 「添加卡片」：加到选中的容器里，选中的是普通卡片就加在它后面。后台页面这里列的是这一页的面板。
5. 右边是**预览**，显示的是草稿。在预览里按住 <kbd>Alt</kbd> 点页面，左边选中对应的卡片。
6. 勾着「**实时预览**」（默认）时，每次改动都会自动存成草稿、右边马上刷新；取消勾选就回到手动的「保存草稿并预览」。满意了再 **发布**，发布之前访客看不到任何变化。
7. 「更多」里：丢弃草稿、恢复主题默认布局、导出 / 导入这一页的布局 JSON、退出预览模式。
8. 页面下拉框旁边：**新建页面**（填标题、地址名，选「从卡片开始」或「空白代码页」，可勾「显示网站外壳」「设为网站入口」）；选中自定义页面时还有「页面设置」（改标题、外壳开关、设 / 取消入口；地址名建好后不能改）和「删除页面」。
9. 写代码时点「**宽屏编辑**」把左边编辑区放宽，再点一次恢复。

**做一个在「文件列表」上面的首页**：新建页面（标题「首页」、地址名 `home`，勾「设为网站入口」）→ 往里加「通用展示」组的卡片 → 发布 → 页面选「前台公共外壳」，点「导航栏」，在「菜单项」里「添加菜单项 → 页面：首页」（页面默认加在最前面）→ 发布。

**预览模式**：打开编辑器时，浏览器会拿到一个半小时有效的预览凭证，这期间你在同一个浏览器里打开前台、后台，看到的都是**正在编辑的主题和草稿**，右下角有「正在预览草稿」的提示条，点「退出预览」恢复正常。只有你自己看得到，访客不受影响。

导出的布局 JSON 可以直接放进主题包的 `layouts/` 当默认布局。

---

## 11. 常见问题

**我的样式不生效。** 看 `<body>` 上的类名，选择器用 `body.theme-<你的id>` 开头；后台用 `body.admin-theme-<你的id>.admin-body`。改了样式要重新上传主题包；主题文件的地址带着按文件修改时间算的版本参数，重新上传后浏览器会取新文件。

**内置外观的样式在哪？** 内置外观的样式在程序的 `assets/css/style.css`（后台在 `admin.css`）里，按 `.theme-<id>` 区分。继承内置外观时这些样式照样生效。

**能不能在模板里写 if / 调函数？** 不能。Mustache 只能判断「有没有」。需要不同情况不同输出，用主题设置的开关（`{{#theme.settings.dense}}`）或卡片配置的开关。

**主题卡片能读数据库吗？** 不能。主题卡片只做展示；文件列表、统计这类数据用程序自带的卡片，可以覆盖它们的模板换样子。

**链接配置里能写 `javascript:` 吗？** 卡片配置只有管理员能改，程序不拦；但别这么写。

**升级程序后主题坏了？** 程序更新可能改卡片的数据和模板。覆盖过的模板要对照新版 `includes/templates/` 里的同名文件检查一遍。模板出错时页面会退回默认模板，错误记录在「主题包」面板最下面。
