var contextarray = [];

var defaults = {
    html       : false,        // 是否启用 HTML 标签
    xhtmlOut   : false,        // 使用 '/' 来关闭单标签 (<br />)
    breaks     : false,        // 将段落中的 '\n' 转换为 <br>
    langPrefix : 'language-',  // 代码块的 CSS 语言前缀
    linkify    : true,         // 自动将 URL 文本转换为链接
    linkTarget : '',           // 设置链接的打开方式
    typographer: true,         // 启用 smartypants 和其他转换
    _highlight : true,         // 是否启用代码高亮
    _strict    : false,        // 是否启用严格模式
    _view      : 'html'        // 预览模式，默认为 HTML
};

defaults.highlight = function (str, lang) {
    if (!defaults._highlight || !window.hljs) {
        return '';
    }

    var hljs = window.hljs;
    if (lang && hljs.getLanguage(lang)) {
        try {
            return hljs.highlight(lang, str).value;
        }
        catch (__) {
        }
    }

    try {
        return hljs.highlightAuto(str).value;
    }
    catch (__) {
    }

    return '';
};
mdHtml = new window.Remarkable('full', defaults);

mdHtml.renderer.rules.table_open = function () {
    return '<table class="table table-hover table-vcenter border-secondary" style="border-radius: 0.25rem;">\n';
};

mdHtml.renderer.rules.paragraph_open = function (tokens, idx) {
    var line;
    if (tokens[idx].lines && tokens[idx].level === 0) {
        line = tokens[idx].lines[0];
        return '<p class="line" data-line="' + line + '">';
    }
    return '<p>';
};

mdHtml.renderer.rules.heading_open = function (tokens, idx) {
    var line;
    if (tokens[idx].lines && tokens[idx].level === 0) {
        line = tokens[idx].lines[0];
        return '<h' + tokens[idx].hLevel + ' class="line" data-line="' + line + '">';
    }
    return '<h' + tokens[idx].hLevel + '>';
};

function getCookie (name)
{
    var cookies = document.cookie.split(';');
    for (var i = 0; i < cookies.length; i++) {
        var cookie = cookies[i].trim();
        if (cookie.indexOf(name + '=') === 0) {
            return cookie.substring(name.length + 1, cookie.length);
        }
    }
    return null;
}


function cacheHtmlById (id, key)
{
    const node = document.getElementById(id);
    const html = node.innerHTML;
    localStorage.setItem(key, JSON.stringify(html));
}

function getCachedHtml (key)
{
    const cachedHtml = localStorage.getItem(key);
    return JSON.parse(cachedHtml);
}

function addCachedHtmlById (id, key)
{
    const cachedHtml = getCachedHtml(key);
    const el = document.getElementById(id);
    if (el && cachedHtml !== null && cachedHtml !== undefined) {
        el.innerHTML = cachedHtml;
        return true;
    }
    else {
        return false;
    }
}


function isMobile ()
{
    const userAgent = navigator.userAgent.toLowerCase();
    const mobileKeywords = [
        'iphone', 'ipod', 'ipad', 'android', 'windows phone', 'blackberry', 'nokia', 'opera mini', 'mobile'
    ];
    for (let i = 0; i < mobileKeywords.length; i++) {
        if (userAgent.indexOf(mobileKeywords[i]) !== -1) {
            return true;
        }
    }
    return false;
}

function insertPresetText ()
{
    $("#msg_context").val($('#preset-text').val());
    layer.closeAll();
    autoresize();
}

function copyToClipboard (text)
{
    var input = document.createElement('textarea');
    input.innerHTML = text;
    document.body.appendChild(input);
    input.select();
    var result = document.execCommand('copy');
    document.body.removeChild(input);
    return result;
}

function copycode (obj)
{
    copyToClipboard($(obj).closest('code').clone().children('button').remove().end().text());
    layer.msg("复制完成！");
}

function isEmpty (str)
{
    return str.trim() === '';
}

function autoresize ()
{
    var textarea = $('#msg_context');
    var width = textarea.width();
    var content = (textarea.val() + "a").replace(/\\n/g, '<br>');
    var div = $('<div>').css({
        'position'   : 'absolute',
        'top'        : '-99999px',
        'border'     : '1px solid red',
        'width'      : width,
        'font-size'  : '15px',
        'line-height': '20px',
        'white-space': 'pre-wrap'
    }).html(content).appendTo('body');
    var height = div.height();
    var rows = Math.ceil(height / 20);
    div.remove();
    textarea.attr('rows', rows);
    $("#article-wrapper").height(parseInt($(window).height()) - parseInt($("#fixed-block").height()) - parseInt($(".layout-header").height()) - 80);
}


$(document).ready(function () {
    autoresize();
    $("#msg_context").on('keydown', function (event) {
        if (event.keyCode === 13 && event.ctrlKey) {
            send_post();
            return false;
        }
    });

    $(window).resize(function () {
        autoresize();
    });

    $('#msg_context').on('input', function () {
        autoresize();
    });

});

function scrollToBottom ()
{
    const scrollHeight = document.body.scrollHeight;
    window.scroll(0, scrollHeight);
}


function send_post ()
{
    const prompt = $("#msg_context").val()

    if (isEmpty(prompt)) {
        layer.msg("请输入您的问题", {icon: 5});
        return;
    }

    const loading = layer.msg('请稍等片刻...', {
        icon : 16,
        shade: 0.4,
        time : false //取消自动关闭
    })

    function streaming ()
    {
        var es = new EventSource("ajax.php?act=comps");
        var isstarted = true;
        var alltext = "";
        var isalltext = false;
        var userCK = 'user_' + getCookie('user');

        if (!userCK) {
            document.cookie = 'user_A=CloudZA; expires=Fri, 31 Dec 9999 23:59:59 GMT; path=/';
            userCK = 'user_A'
        }
        es.onerror = function (event) {
            layer.close(loading);
            var errcode = getCookie("errCode");
            switch (errcode) {
                case "invalid_api_key":
                    app.btn('Secret Key 不合法', '我知道了')
                    break;
                case "context_length_exceeded":
                    app.btn('问题和上下文长度超限，请重新提问', '我知道了')
                    break;
                case "rate_limit_reached":
                    app.btn('同时访问用户过多，请稍后再试', '我知道了')
                    break;
                case "access_terminated":
                    app.btn('违规使用，Secret Key被封禁', '我知道了')
                    break;
                case "no_api_key":
                    app.btn('未提供Secret Key', '我知道了')
                    break;
                case "insufficient_quota":
                    app.btn('Secret Key余额不足', '我知道了')
                    break;
                case "account_deactivated":
                    app.btn('账户已禁用，请更换Secret Key', '我知道了')
                    break;
                case "model_overloaded":
                    app.btn('OpenAI模型超负荷，请重新发起请求', '我知道了')
                    break;
                case null:
                    app.btn('OpenAI服务器访问超时或未知类型错误', '我知道了')
                    break;
                default:
                    app.btn("OpenAI服务器故障，错误类型：" + errcode, '我知道了')
            }
            es.close();
            if (!isMobile()) $("#msg_context").focus();
        }
        es.onmessage = function (event) {
            if (isstarted) {
                layer.close(loading);
                $("#msg_context").val("请等待……");
                $("#msg_context").attr("disabled", true);
                autoresize();
                $("#chatBoxMsg").hide()
                $("#send_message").html('<i class="text-danger far fa-circle-stop opacity-50"></i> 中止 ');
                // layer.msg("处理成功！");
                isstarted = false;
                answer = randomString(16);
                $("#article-wrapper").append('<div class="clearfloat"><div class="right"><div class="chat-message fs-sm" id="q' + answer + '"><span style="margin-bottom: 0;"></span></div><div class="chat-avatars"><svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" t="1680461072428" class="icon" viewBox="0 0 1024 1024" version="1.1" p-id="19281" width="35" height="35"><path d="M512 512.002m-511.998 0a511.998 511.998 0 1 0 1023.996 0 511.998 511.998 0 1 0-1023.996 0Z" fill="#552C7A" p-id="19282"/><path d="M511.998 512.002m-407.00441 0a407.00441 407.00441 0 1 0 814.00882 0 407.00441 407.00441 0 1 0-814.00882 0Z" fill="#774197" p-id="19283"/><path d="M561.049808 655.681439v-49.789806l56.773779-59.443768 21.311916-126.887504 10.523959 4.923981 1.351995-7.875969 15.31194-89.155652-44.667825-149.333417-159.999375-48.66581-47.955813 31.761876-3.375987 2.235992-24.871903-2.983989v-0.002l-20.37192-2.44399-72.089718 108.095577 50.941801 188.065266v-0.004l15.499939 92.269639 56.769778 59.443768v49.789806l-155.875391 79.999687v84.797669c0 63.451752 102.213601 114.885551 228.299108 114.885551s228.295108-51.435799 228.295109-114.885551v-84.797669l-155.871392-79.999687z" fill="#552C7A" p-id="19284"/><path d="M396.032453 499.740048l13.025949 79.999687 48.319811 33.999868 5.679978-81.331683zM628.053547 499.740048l-13.025949 79.999687-48.319812 33.999868-5.679978-81.331683z" fill="#DDAE2A" p-id="19285"/><path d="M616.769591 505.240026l-12.133953 74.499709-38.41385 27.031895 0.485998 6.967973 48.319812-33.999868 13.025949-79.999687z" fill="#EDC849" p-id="19286"/><path d="M584.421717 619.871579h-144.847434l-155.875391 79.999687v84.797669c0 63.451752 102.213601 114.885551 228.299108 114.885551s228.295108-51.435799 228.295108-114.885551v-84.797669l-155.871391-79.999687z" fill="#F3F4F5" p-id="19287"/><path d="M317.640759 699.871266l155.875391-79.999687h-33.941867l-155.875391 79.999687v84.797669c0 22.113914 12.429951 42.765833 33.941867 60.291764v-145.089433z" fill="#E6E5E4" p-id="19288"/><path d="M584.421717 637.871508L512 688.305311l-72.425717-50.667802v-149.331416h144.847434z" fill="#DE8729" p-id="19289"/><path d="M561.027808 488.306093v165.857352l23.393909-16.291937v-149.565415z" fill="#F79D22" p-id="19290"/><path d="M677.359354 295.328846h-0.479998c0.285999-3.965985 0.479998-7.957969 0.479998-11.999953 0-91.325643-74.035711-165.359354-165.359354-165.359354-91.327643 0-165.359354 74.033711-165.359354 165.359354 0 4.041984 0.195999 8.033969 0.479998 11.999953h-0.479998l0.945996 5.629978a164.065359 164.065359 0 0 0 3.229988 19.233925l31.987875 190.445256 92.417639 96.769622H548.773856l92.421639-96.769622 31.983875-190.445256a164.631357 164.631357 0 0 0 3.233988-19.233925l0.945996-5.629978z" fill="#F79D22" p-id="19291"/><path d="M517.323979 333.796696l143.139441 62.111757 3.887985-23.139909-6.527975-40.99184-77.241698-142.471443h-119.367534l-33.685868 24.203905 6.167976 42.797833 25.869899-11.441956 24.203905 11.141957zM406.91441 295.328846H346.640646l36.163859 215.309159 92.417639 96.769622h13.405947l-58.73377-96.769622z" fill="#DE8729" p-id="19292"/><path d="M499.20005 467.052176l9.999961-12.159953v-110.591568l-10.571959-32.141874h-9.999961l10.571959 32.475873zM531.699923 454.892223H509.200011l-9.999961 12.159953h42.499834z" fill="#DE8729" p-id="19293"/><path d="M546.475865 346.448647l102.991598 44.691825c0.106-1.459994 0.179999-2.927989 0.179999-4.413983 0-32.835872-26.617896-59.453768-59.449767-59.453767-17.291932 0-32.853872 7.391971-43.72183 19.175925z" fill="#1669AD" p-id="19294"/><path d="M654.319444 393.242464c0.213999-2.141992 0.327999-4.317983 0.327999-6.517975 0-35.539861-28.909887-64.453748-64.449748-64.453748-19.335924 0-36.695857 8.569967-48.519811 22.093914l9.709962 4.213983c9.881961-10.057961 23.625908-16.307936 38.807849-16.307936 30.023883 0 54.449787 24.427905 54.449787 54.453787 0 0.769997-0.028 1.533994-0.058 2.295991l9.731962 4.221984z" fill="#323232" p-id="19295"/><path d="M528.097937 383.438502c-15.167941-9.481963-28.389889-0.263999-28.523888-0.169999l-5.815978-8.135968c0.761997-0.541998 18.863926-13.165949 39.639845-0.17l-5.299979 8.475967zM362.594584 372.558545h16.351936v9.999961h-16.351936z" fill="#494A4A" p-id="19296"/><path d="M649.647462 372.558545h16.355936v9.999961h-16.355936z" fill="#323232" p-id="19297"/><path d="M406.91441 295.328846l-2.217991-36.355858 12.93795-10.999957 16.061937 8.333968v-15.33394s-25.999898-16.335936-26.999895-15.667939-36.847856 20.667919-35.925859 22.333913c0.925996 1.665993 2.47199 37.665853 2.47199 37.665853l23.453908 22.667911 10.21796-12.643951zM427.52833 311.118785l71.061722 11.017957v-9.977961l-71.061722-11.019957z" fill="#DE8729" p-id="19298"/><path d="M427.52833 311.118785l71.061722 11.017957v-9.977961l-71.061722-11.019957z" fill="#DE8729" p-id="19299"/><path d="M364.472576 333.796696l67.835735-23.861907-4.713981-8.795965-67.835735 23.865906z" fill="#DE8729" p-id="19300"/><path d="M469.878165 307.972797l-52.245796-67.665736-21.599916 3.831985-5.503979 67.499737 21.349917-3.665986z" fill="#DE8729" p-id="19301"/><path d="M520.449967 496.138062h-67.235737l9.843961 16.499936 57.391776 24.999902 57.389776-24.999902 9.843961-16.499936z" fill="#BE3D27" p-id="19302"/><path d="M453.21423 496.138062l8.589966 14.395944h117.293542l8.585966-14.395944h-67.233737z" fill="#E8E9EC" p-id="19303"/><path d="M829.290761 229.391104c-70.893723 0-128.367499 57.471776-128.367499 128.367499 0 41.327839 19.553924 78.069695 49.889805 101.547603l-81.523681 44.029828 106.581583-28.859887a127.853501 127.853501 0 0 0 53.417792 11.647954c70.893723 0 128.367499-57.471776 128.367498-128.367498s-57.469776-128.365499-128.365498-128.365499z" fill="#3AB49C" p-id="19304"/><path d="M899.920485 437.66629a14.653943 14.653943 0 0 1-18.901926 8.499967l-141.117449-53.60379a14.655943 14.655943 0 0 1-8.499967-18.903927l32.503873-85.571665a14.655943 14.655943 0 0 1 18.905926-8.497967l141.117449 53.60379a14.649943 14.649943 0 0 1 8.495967 18.903927l-32.503873 85.569665z" fill="#F3F4F5" p-id="19305"/><path d="M829.53076 369.170558l-55.945782-81.345682a1.465994 1.465994 0 1 1 2.417991-1.661994l54.785786 79.661689 92.859637-23.577908a1.473994 1.473994 0 0 1 1.781993 1.059996 1.469994 1.469994 0 0 1-1.061996 1.783993l-94.837629 24.079906z" fill="#494A4A" p-id="19306"/><path d="M497.144058 357.272604c0-11.045957-8.953965-19.999922-19.999922-19.999921h-78.901692c-11.045957 0-19.999922 8.953965-19.999922 19.999921v58.90577c0 11.045957 8.953965 19.999922 19.999922 19.999922h78.901692c11.045957 0 19.999922-8.953965 19.999922-19.999922v-58.90577z" fill="#2A81C2" p-id="19307"/><path d="M477.144136 441.178277h-78.901692c-13.785946 0-24.999902-11.213956-24.999902-24.999903v-58.90577c0-13.785946 11.213956-24.999902 24.999902-24.999902h78.901692c13.785946 0 24.999902 11.213956 24.999902 24.999902v58.90577c0 13.783946-11.213956 24.999902-24.999902 24.999903z m-78.901692-98.905614c-8.269968 0-14.999941 6.727974-14.999941 14.999941v58.90577c0 8.271968 6.729974 14.999941 14.999941 14.999942h78.901692c8.273968 0 14.999941-6.727974 14.999942-14.999942v-58.90577c0-8.271968-6.725974-14.999941-14.999942-14.999941h-78.901692z" fill="#494A4A" p-id="19308"/><path d="M649.647462 357.272604c0-11.045957-8.953965-19.999922-19.999922-19.999921h-78.901691c-11.045957 0-19.999922 8.953965-19.999922 19.999921v58.90577c0 11.045957 8.953965 19.999922 19.999922 19.999922h78.901691c11.045957 0 19.999922-8.953965 19.999922-19.999922v-58.90577z" fill="#2A81C2" p-id="19309"/><path d="M629.64754 441.178277h-78.901691c-13.785946 0-24.999902-11.213956-24.999903-24.999903v-58.90577c0-13.785946 11.213956-24.999902 24.999903-24.999902h78.901691c13.785946 0 24.999902 11.213956 24.999903 24.999902v58.90577c0 13.783946-11.213956 24.999902-24.999903 24.999903z m-78.901691-98.905614c-8.269968 0-14.999941 6.727974-14.999942 14.999941v58.90577c0 8.271968 6.729974 14.999941 14.999942 14.999942h78.901691c8.273968 0 14.999941-6.727974 14.999942-14.999942v-58.90577c0-8.271968-6.725974-14.999941-14.999942-14.999941h-78.901691z" fill="#494A4A" p-id="19310"/><path d="M437.69629 386.724489m-10.377959 0a10.377959 10.377959 0 1 0 20.755919 0 10.377959 10.377959 0 1 0-20.755919 0Z" fill="#1669AD" p-id="19311"/><path d="M590.199695 386.724489m-10.37796 0a10.377959 10.377959 0 1 0 20.755919 0 10.377959 10.377959 0 1 0-20.755919 0Z" fill="#1669AD" p-id="19312"/><path d="M417.632369 285.530885l102.817598 72.225718 152.581404 30.917879 16.663935-97.033621-44.667826-149.333417-159.999375-48.66581-51.331799 33.999867-45.241823-5.427978-72.089719 108.093577 50.939801 188.067266 5.727978-64.06775 20.33192-29.033887-1.039996-69.265729z" fill="#DDAE2A" p-id="19313"/><path d="M645.02748 142.307444l-159.999375-48.66581-6.421975 4.251984 146.01943 44.413826 44.665826 149.333417-15.979938 93.037636 19.719923 3.995985 16.663935-97.033621zM378.242522 292.608857l-5.281979 32.663872-20.33192 29.033887-0.663998 7.429971 15.33994 56.637779 5.727978-64.06775 20.33192-29.033887-1.039996-69.265729z" fill="#EDC849" p-id="19314"/></svg></div></div></div>');
                for (var j = 0; j < prompt.length; j++) {
                    $("#q" + answer).children('span').text($("#q" + answer).children('span').text() + prompt[j]);
                }
                $("#article-wrapper").append('<div class="clearfloat"> <div class="left"><div class="chat-avatars"><svg width="35" height="35" viewBox="0 0 41 41" fill="none" xmlns="http://www.w3.org/2000/svg" stroke-width="1.5" class="h-6 w-6"><path d="M37.5324 16.8707C37.9808 15.5241 38.1363 14.0974 37.9886 12.6859C37.8409 11.2744 37.3934 9.91076 36.676 8.68622C35.6126 6.83404 33.9882 5.3676 32.0373 4.4985C30.0864 3.62941 27.9098 3.40259 25.8215 3.85078C24.8796 2.7893 23.7219 1.94125 22.4257 1.36341C21.1295 0.785575 19.7249 0.491269 18.3058 0.500197C16.1708 0.495044 14.0893 1.16803 12.3614 2.42214C10.6335 3.67624 9.34853 5.44666 8.6917 7.47815C7.30085 7.76286 5.98686 8.3414 4.8377 9.17505C3.68854 10.0087 2.73073 11.0782 2.02839 12.312C0.956464 14.1591 0.498905 16.2988 0.721698 18.4228C0.944492 20.5467 1.83612 22.5449 3.268 24.1293C2.81966 25.4759 2.66413 26.9026 2.81182 28.3141C2.95951 29.7256 3.40701 31.0892 4.12437 32.3138C5.18791 34.1659 6.8123 35.6322 8.76321 36.5013C10.7141 37.3704 12.8907 37.5973 14.9789 37.1492C15.9208 38.2107 17.0786 39.0587 18.3747 39.6366C19.6709 40.2144 21.0755 40.5087 22.4946 40.4998C24.6307 40.5054 26.7133 39.8321 28.4418 38.5772C30.1704 37.3223 31.4556 35.5506 32.1119 33.5179C33.5027 33.2332 34.8167 32.6547 35.9659 31.821C37.115 30.9874 38.0728 29.9178 38.7752 28.684C39.8458 26.8371 40.3023 24.6979 40.0789 22.5748C39.8556 20.4517 38.9639 18.4544 37.5324 16.8707ZM22.4978 37.8849C20.7443 37.8874 19.0459 37.2733 17.6994 36.1501C17.7601 36.117 17.8666 36.0586 17.936 36.0161L25.9004 31.4156C26.1003 31.3019 26.2663 31.137 26.3813 30.9378C26.4964 30.7386 26.5563 30.5124 26.5549 30.2825V19.0542L29.9213 20.998C29.9389 21.0068 29.9541 21.0198 29.9656 21.0359C29.977 21.052 29.9842 21.0707 29.9867 21.0902V30.3889C29.9842 32.375 29.1946 34.2791 27.7909 35.6841C26.3872 37.0892 24.4838 37.8806 22.4978 37.8849ZM6.39227 31.0064C5.51397 29.4888 5.19742 27.7107 5.49804 25.9832C5.55718 26.0187 5.66048 26.0818 5.73461 26.1244L13.699 30.7248C13.8975 30.8408 14.1233 30.902 14.3532 30.902C14.583 30.902 14.8088 30.8408 15.0073 30.7248L24.731 25.1103V28.9979C24.7321 29.0177 24.7283 29.0376 24.7199 29.0556C24.7115 29.0736 24.6988 29.0893 24.6829 29.1012L16.6317 33.7497C14.9096 34.7416 12.8643 35.0097 10.9447 34.4954C9.02506 33.9811 7.38785 32.7263 6.39227 31.0064ZM4.29707 13.6194C5.17156 12.0998 6.55279 10.9364 8.19885 10.3327C8.19885 10.4013 8.19491 10.5228 8.19491 10.6071V19.808C8.19351 20.0378 8.25334 20.2638 8.36823 20.4629C8.48312 20.6619 8.64893 20.8267 8.84863 20.9404L18.5723 26.5542L15.206 28.4979C15.1894 28.5089 15.1703 28.5155 15.1505 28.5173C15.1307 28.5191 15.1107 28.516 15.0924 28.5082L7.04046 23.8557C5.32135 22.8601 4.06716 21.2235 3.55289 19.3046C3.03862 17.3858 3.30624 15.3413 4.29707 13.6194ZM31.955 20.0556L22.2312 14.4411L25.5976 12.4981C25.6142 12.4872 25.6333 12.4805 25.6531 12.4787C25.6729 12.4769 25.6928 12.4801 25.7111 12.4879L33.7631 17.1364C34.9967 17.849 36.0017 18.8982 36.6606 20.1613C37.3194 21.4244 37.6047 22.849 37.4832 24.2684C37.3617 25.6878 36.8382 27.0432 35.9743 28.1759C35.1103 29.3086 33.9415 30.1717 32.6047 30.6641C32.6047 30.5947 32.6047 30.4733 32.6047 30.3889V21.188C32.6066 20.9586 32.5474 20.7328 32.4332 20.5338C32.319 20.3348 32.154 20.1698 31.955 20.0556ZM35.3055 15.0128C35.2464 14.9765 35.1431 14.9142 35.069 14.8717L27.1045 10.2712C26.906 10.1554 26.6803 10.0943 26.4504 10.0943C26.2206 10.0943 25.9948 10.1554 25.7963 10.2712L16.0726 15.8858V11.9982C16.0715 11.9783 16.0753 11.9585 16.0837 11.9405C16.0921 11.9225 16.1048 11.9068 16.1207 11.8949L24.1719 7.25025C25.4053 6.53903 26.8158 6.19376 28.2383 6.25482C29.6608 6.31589 31.0364 6.78077 32.2044 7.59508C33.3723 8.40939 34.2842 9.53945 34.8334 10.8531C35.3826 12.1667 35.5464 13.6095 35.3055 15.0128ZM14.2424 21.9419L10.8752 19.9981C10.8576 19.9893 10.8423 19.9763 10.8309 19.9602C10.8195 19.9441 10.8122 19.9254 10.8098 19.9058V10.6071C10.8107 9.18295 11.2173 7.78848 11.9819 6.58696C12.7466 5.38544 13.8377 4.42659 15.1275 3.82264C16.4173 3.21869 17.8524 2.99464 19.2649 3.1767C20.6775 3.35876 22.0089 3.93941 23.1034 4.85067C23.0427 4.88379 22.937 4.94215 22.8668 4.98473L14.9024 9.58517C14.7025 9.69878 14.5366 9.86356 14.4215 10.0626C14.3065 10.2616 14.2466 10.4877 14.2479 10.7175L14.2424 21.9419ZM16.071 17.9991L20.4018 15.4978L24.7325 17.9975V22.9985L20.4018 25.4983L16.071 22.9985V17.9991Z" fill="currentColor"></path></svg></div><div class="chat-message bg-body fs-sm" id="' + answer + '"></div></div></div>');
                let str_ = '';
                let i = 0;
                let isScrollToBottom = false;
                let prevScrollTop = 0;
                timer = setInterval(() => {
                    let newalltext = alltext;
                    let islastletter = false;
                    //有时服务器错误地返回\\n作为换行符，尤其是包含上下文的提问时，这行代码可以处理一下。
                    if (newalltext.split("\n\n").length === newalltext.split("\n").length) {
                        newalltext = newalltext.replace(/\\n/g, '\n');
                    }
                    if (str_.length < newalltext.length) {
                        str_ += newalltext[i++];
                        strforcode = str_ + "_";
                        if ((str_.split("```").length % 2) === 0) strforcode += "\n```\n";
                    }
                    else {
                        if (isalltext) {
                            clearInterval(timer);
                            strforcode = str_;
                            islastletter = true;
                            $("#msg_context").val("");
                            $("#msg_context").attr("disabled", false);
                            autoresize();
                            $("#send_message").html('<i class="fa fa-bolt-lightning opacity-50"></i> 发送');
                            if (!isMobile()) $("#msg_context").focus();
                        }
                    }

                    newalltext = mdHtml.render(strforcode);
                    $("#" + answer).html(newalltext);
                    if (islastletter) MathJax.Hub.Queue(["Typeset", MathJax.Hub]);
                    $("#" + answer + " pre code").each(function () {
                        $(this).html("<span onclick='copycode(this);' class='codebutton'>复制代码</span>" + $(this).html());
                    });
                    // 置底
                    const scrollTop = window.pageYOffset;
                    const screenHeight = window.innerHeight;
                    const scrolledDistance = scrollTop + screenHeight;

                    if (!isScrollToBottom && (scrollTop > 0) && (scrollTop === prevScrollTop)) {
                        isScrollToBottom = true;
                    }

                    if (isScrollToBottom && (scrolledDistance + 100 < document.body.scrollHeight)) {
                        isScrollToBottom = false;
                    }

                    if (isScrollToBottom) {
                        scrollToBottom();
                    }

                    prevScrollTop = scrollTop;
                    cacheHtmlById('article-wrapper', userCK) // 缓存聊天记录
                }, 30);


            }
            if (event.data === "[DONE]") {
                isalltext = true;
                contextarray.push([prompt, alltext]);
                contextarray = contextarray.slice(-5); //只保留最近5次对话作为上下文，以免超过最大tokens限制
                es.close();
                return;
            }
            var json = eval("(" + event.data + ")");
            if (json.choices[0].delta.hasOwnProperty("content")) {
                if (alltext === "") {
                    alltext = json.choices[0].delta.content.replace(/^\n+/, ''); //去掉回复消息中偶尔开头就存在的连续换行符
                }
                else {
                    alltext += json.choices[0].delta.content;
                }
            }
        }
    }


    $.ajax({
        url     : 'ajax.php?act=context',
        method  : 'post',
        cache   : false,
        dataType: 'json',
        timeout : 0, // 永不超时
        data    : {
            message: prompt,
            context: (!($("#keep").length) || ($("#keep").prop("checked"))) ? JSON.stringify(contextarray) : '[]',
            key    : ($("#key").length) ? ($("#key").val()) : '',

        },
        success : function (res) {
            if (res.code === 200) {
                streaming();
            }
            else if (res.code === 201) {
                app.btn(res.msg, '我知道了');
            }
            else if (res.code === 301 || res.code === 302 || res.code === 305) {
                app.btn(res.msg, res.data.btn_text, '取消', function () {
                    window.location.href = res.data.url;
                });
            }
            else if (res.code === 306) {
                app.btn(res.msg, res.data.btn_text, '取消', function () {
                    app.pjax(res.data.url);
                });
            }
            else if (res.code === 307) {
                app.btn(res.msg, '我知道了');
            }
            else if (res.code === 500) {
                app.btn(res.msg, '我知道了', null, function () {
                    app.pjax('index.php');
                });
            }
            else {
                app.btn('服务器错误，请联系管理员', '我知道了');
            }
        },
        error   : function () {
            app.btn('AJAX请求失败，请联系管理员', '我知道了');
        }
    });

}

function randomString (len)
{
    len = len || 32;
    var $chars = 'ABCDEFGHJKMNPQRSTWXYZabcdefhijkmnprstwxyz2345678';
    /****默认去掉了容易混淆的字符oOLl,9gq,Vv,Uu,I1****/
    var maxPos = $chars.length;
    var pwd = '';
    for (i = 0; i < len; i++) {
        pwd += $chars.charAt(Math.floor(Math.random() * maxPos));
    }
    return pwd;
}