<?php
/**
 * Admin ads settings
**/
define('IN_ADMIN', true);
include("../includes/common.php");
include SYSTEM_ROOT.'script_manager.php';

if($islogin!=1)exit("<script language='javascript'>window.location.href='./login.php';</script>");

$saved = false;
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['do']) && $_POST['do'] === 'save'){
	if(!checkRefererHost())exit('{"code":403}');

	$ads_enable = isset($_POST['ads_enable']) && $_POST['ads_enable'] == '1' ? '1' : '0';
	$ads_notice_text = isset($_POST['ads_notice_text']) ? trim($_POST['ads_notice_text']) : '';
	$ads_json = mpimg_json(mpimg_ads_from_post($_POST));
	$file_reward_enable = isset($_POST['file_reward_enable']) && $_POST['file_reward_enable'] == '1' ? '1' : '0';
	$file_reward_title = isset($_POST['file_reward_title']) ? trim($_POST['file_reward_title']) : '';
	$file_reward_image = isset($_POST['file_reward_image']) ? trim($_POST['file_reward_image']) : '';

	saveSetting('ads_enable', $ads_enable);
	saveSetting('ads_notice_text', $ads_notice_text);
	saveSetting('ads_json', $ads_json);
	saveSetting('file_reward_enable', $file_reward_enable);
	saveSetting('file_reward_title', $file_reward_title);
	saveSetting('file_reward_image', $file_reward_image);

	// Disable the removed announcement/add entrypoints and keep old gg keys compatible.
	saveSetting('announcement_enable', '0');
	saveSetting('announcement_text', '');
	saveSetting('add_js_enable', '0');
	saveSetting('add_js_text', '');
	saveSetting('gg_js_enable', $ads_enable);
	saveSetting('gg_js_text', $ads_notice_text);
	saveSetting('gg_ads_json', $ads_json);

	$conf = getAllSetting();
	$saved = true;
}

$title = '&#24191;&#21578;&#20844;&#21578;&#20301;&#35774;&#32622;';
include './head.php';

function mpimg_admin_h($value){
	return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function mpimg_admin_form_h($value){
	return htmlspecialchars(html_entity_decode((string)$value, ENT_QUOTES, 'UTF-8'), ENT_QUOTES, 'UTF-8');
}

$ads_enabled = mpimg_conf_enabled_any($conf, ['ads_enable', 'gg_js_enable'], 1);
$ads_notice_text = mpimg_conf_value_any($conf, ['ads_notice_text', 'gg_js_text'], mpimg_default_gg_text());
$ads = mpimg_get_ads($conf);
$file_reward_enable = isset($conf['file_reward_enable']) ? ((int)$conf['file_reward_enable'] === 1) : true;
$file_reward_title = isset($conf['file_reward_title']) && $conf['file_reward_title'] !== '' ? $conf['file_reward_title'] : '&#25195;&#30721;&#39046;&#32418;&#21253;';
$file_reward_image = isset($conf['file_reward_image']) && $conf['file_reward_image'] !== '' ? $conf['file_reward_image'] : 'includes/sponsor/images/zhifubaohb.jpg';
?>
<style>
.script-settings-page{padding-top:70px}
.script-settings-shell{float:none}
.script-settings-form{display:block}
.script-settings-panel{border-radius:18px!important;overflow:hidden}
.script-settings-panel>.panel-heading{padding:18px 22px!important}
.script-settings-panel>.panel-heading .panel-title{font-size:18px;display:flex;align-items:center;gap:10px}
.script-settings-panel>.panel-heading .panel-title:before{font-family:FontAwesome;font-size:16px}
.script-settings-panel.script-panel-main>.panel-heading .panel-title:before{content:"\f0a1"}
.script-settings-panel.script-panel-reward>.panel-heading .panel-title:before{content:"\f029"}
.script-settings-panel.script-panel-action>.panel-body{padding:16px 20px}
.script-settings-panel>.panel-body{padding:22px}
.script-settings-panel .form-group{margin-left:0;margin-right:0;padding:18px 0;border-bottom:1px solid rgba(148,163,184,.14)}
.script-settings-panel .form-group:first-child{padding-top:0}
.script-settings-panel .form-group:last-of-type{border-bottom:0;padding-bottom:0}
.script-settings-panel .control-label{padding-left:0;padding-right:18px;font-size:14px;font-weight:800;line-height:20px;color:inherit;text-align:left}
.script-settings-panel .form-control{min-height:40px;border-radius:12px}
.script-settings-panel textarea.form-control{min-height:132px;line-height:1.75;resize:vertical}
.script-settings-panel select.form-control{padding-right:34px}
.script-help{margin-top:10px;color:#64748b;line-height:1.8;font-size:13px}
.script-toolbar{display:flex;align-items:center;justify-content:space-between;gap:16px;margin:18px 0 14px;padding:16px 18px;border:1px solid rgba(148,163,184,.16);border-radius:14px;background:rgba(255,255,255,.56);flex-wrap:wrap}
.script-toolbar strong{display:flex;align-items:center;gap:8px;font-size:15px}
.script-toolbar strong:before{content:"\f0ce";font-family:FontAwesome;color:var(--admin-primary)}
.script-ad-table-wrap{border:1px solid rgba(148,163,184,.16);border-radius:16px;overflow:hidden;background:rgba(255,255,255,.72)}
.script-ad-table{margin-bottom:0}
.script-ad-table>thead>tr>th{font-size:13px;font-weight:800;text-align:center;vertical-align:middle}
.script-ad-table>tbody>tr>td{vertical-align:middle;text-align:center;padding:14px 10px}
.script-ad-table>tbody>tr>td:nth-child(3),
.script-ad-table>tbody>tr>td:nth-child(4),
.script-ad-table>tbody>tr>td:nth-child(5),
.script-ad-table>tbody>tr>td:nth-child(7){text-align:left}
.script-ad-table .form-control{min-width:120px}
.script-ad-table .script-image-input{min-width:180px}
.script-ad-table input[type="checkbox"]{width:18px;height:18px;margin:0}
.script-ad-table input[type="color"]{width:56px;min-width:56px;height:38px;padding:4px;margin:0 auto;border-radius:10px}
.script-ad-table .btn-danger{min-width:68px}
.script-action-bar{display:flex;gap:10px;align-items:center;justify-content:flex-end;flex-wrap:wrap}
.script-empty-row td{padding:28px 14px!important;color:#71839c;text-align:center!important;font-size:14px}
body.admin-theme-night.admin-body .script-toolbar,
body.admin-theme-neon.admin-body .script-toolbar,
body.admin-theme-aurora.admin-body .script-toolbar,
body.admin-theme-celadon.admin-body .script-toolbar,
body.admin-theme-lilac.admin-body .script-toolbar,
body.admin-theme-paper.admin-body .script-toolbar,
body.admin-theme-blush.admin-body .script-toolbar,
body.admin-theme-sky.admin-body .script-toolbar,
body.admin-theme-mint.admin-body .script-toolbar,
body.admin-theme-sunset.admin-body .script-toolbar,
body.admin-theme-abyss.admin-body .script-toolbar,
body.admin-theme-emerald.admin-body .script-toolbar,
body.admin-theme-sakura.admin-body .script-toolbar,
body.admin-theme-onefour.admin-body .script-toolbar,
body.admin-theme-night.admin-body .script-ad-table-wrap,
body.admin-theme-neon.admin-body .script-ad-table-wrap,
body.admin-theme-aurora.admin-body .script-ad-table-wrap,
body.admin-theme-celadon.admin-body .script-ad-table-wrap,
body.admin-theme-lilac.admin-body .script-ad-table-wrap,
body.admin-theme-paper.admin-body .script-ad-table-wrap,
body.admin-theme-blush.admin-body .script-ad-table-wrap,
body.admin-theme-sky.admin-body .script-ad-table-wrap,
body.admin-theme-mint.admin-body .script-ad-table-wrap,
body.admin-theme-sunset.admin-body .script-ad-table-wrap,
body.admin-theme-abyss.admin-body .script-ad-table-wrap,
body.admin-theme-emerald.admin-body .script-ad-table-wrap,
body.admin-theme-sakura.admin-body .script-ad-table-wrap,
body.admin-theme-onefour.admin-body .script-ad-table-wrap{background:transparent}
body.admin-theme-night.admin-body .script-settings-panel .form-group,
body.admin-theme-neon.admin-body .script-settings-panel .form-group,
body.admin-theme-aurora.admin-body .script-settings-panel .form-group,
body.admin-theme-celadon.admin-body .script-settings-panel .form-group,
body.admin-theme-lilac.admin-body .script-settings-panel .form-group,
body.admin-theme-paper.admin-body .script-settings-panel .form-group,
body.admin-theme-blush.admin-body .script-settings-panel .form-group,
body.admin-theme-sky.admin-body .script-settings-panel .form-group,
body.admin-theme-mint.admin-body .script-settings-panel .form-group,
body.admin-theme-sunset.admin-body .script-settings-panel .form-group,
body.admin-theme-abyss.admin-body .script-settings-panel .form-group,
body.admin-theme-emerald.admin-body .script-settings-panel .form-group,
body.admin-theme-sakura.admin-body .script-settings-panel .form-group,
body.admin-theme-onefour.admin-body .script-settings-panel .form-group,
body.admin-theme-night.admin-body .script-toolbar,
body.admin-theme-neon.admin-body .script-toolbar,
body.admin-theme-aurora.admin-body .script-toolbar,
body.admin-theme-celadon.admin-body .script-toolbar,
body.admin-theme-lilac.admin-body .script-toolbar,
body.admin-theme-paper.admin-body .script-toolbar,
body.admin-theme-blush.admin-body .script-toolbar,
body.admin-theme-sky.admin-body .script-toolbar,
body.admin-theme-mint.admin-body .script-toolbar,
body.admin-theme-sunset.admin-body .script-toolbar,
body.admin-theme-abyss.admin-body .script-toolbar,
body.admin-theme-emerald.admin-body .script-toolbar,
body.admin-theme-sakura.admin-body .script-toolbar,
body.admin-theme-onefour.admin-body .script-toolbar,
body.admin-theme-night.admin-body .script-ad-table-wrap,
body.admin-theme-neon.admin-body .script-ad-table-wrap,
body.admin-theme-aurora.admin-body .script-ad-table-wrap,
body.admin-theme-celadon.admin-body .script-ad-table-wrap,
body.admin-theme-lilac.admin-body .script-ad-table-wrap,
body.admin-theme-paper.admin-body .script-ad-table-wrap,
body.admin-theme-blush.admin-body .script-ad-table-wrap,
body.admin-theme-sky.admin-body .script-ad-table-wrap,
body.admin-theme-mint.admin-body .script-ad-table-wrap,
body.admin-theme-sunset.admin-body .script-ad-table-wrap,
body.admin-theme-abyss.admin-body .script-ad-table-wrap,
body.admin-theme-emerald.admin-body .script-ad-table-wrap,
body.admin-theme-sakura.admin-body .script-ad-table-wrap,
body.admin-theme-onefour.admin-body .script-ad-table-wrap{border-color:rgba(148,163,184,.18)}
@media (max-width:767px){
	.script-settings-page{padding-top:64px}
	.script-settings-panel>.panel-body{padding:16px}
	.script-settings-panel>.panel-heading{padding:16px!important}
	.script-settings-panel .form-group{padding:14px 0}
	.script-settings-panel .control-label{padding-right:0;margin-bottom:8px}
	.script-action-bar{justify-content:stretch}
	.script-action-bar .btn,.script-toolbar .btn{width:100%}
	.script-toolbar{padding:14px}
	.script-ad-table-wrap{border-radius:14px}
	.script-ad-table>thead>tr>th,.script-ad-table>tbody>tr>td{white-space:nowrap}
}
</style>
<div class="container script-settings-page">
  <div class="col-xs-12 col-sm-11 col-lg-10 center-block script-settings-shell">
	<?php if($saved){?>
	<div class="alert alert-success">&#35774;&#32622;&#20445;&#23384;&#25104;&#21151;&#65292;&#21069;&#21488;&#21047;&#26032;&#21518;&#20250;&#31435;&#21363;&#35835;&#21462;&#26032;&#30340;&#20869;&#23481;&#12290;</div>
	<?php }?>

	<form action="./set_script.php" method="post" class="form-horizontal script-settings-form" role="form">
	  <input type="hidden" name="do" value="save"/>

	  <div class="panel panel-primary script-settings-panel script-panel-main">
	    <div class="panel-heading"><h3 class="panel-title">&#24191;&#21578;&#20844;&#21578;&#19982;&#24191;&#21578;&#20301;</h3></div>
	    <div class="panel-body">
		  <div class="form-group">
		    <label class="col-sm-2 control-label">&#24320;&#20851;</label>
		    <div class="col-sm-10">
		      <select class="form-control" name="ads_enable">
		        <option value="1" <?php echo $ads_enabled?'selected':null;?>>&#24320;&#21551;</option>
		        <option value="0" <?php echo !$ads_enabled?'selected':null;?>>&#20851;&#38381;</option>
		      </select>
		    </div>
		  </div>
		  <div class="form-group">
		    <label class="col-sm-2 control-label">&#20844;&#21578;&#20869;&#23481;</label>
		    <div class="col-sm-10">
		      <textarea class="form-control" name="ads_notice_text" rows="5"><?php echo mpimg_admin_form_h($ads_notice_text);?></textarea>
		    </div>
		  </div>

		  <div class="script-toolbar">
		    <strong>&#24191;&#21578;&#20301;&#21015;&#34920;</strong>
		    <button type="button" class="btn btn-success btn-sm" id="addAdRow"><i class="fa fa-plus"></i> &#28155;&#21152;&#24191;&#21578;</button>
		  </div>
		  <div class="table-responsive script-ad-table-wrap">
		    <table class="table table-bordered table-hover script-ad-table">
		      <thead>
		        <tr>
		          <th style="width:70px;">&#26174;&#31034;</th>
		          <th style="width:120px;">&#24191;&#21578;&#31867;&#22411;</th>
		          <th style="width:150px;">&#24191;&#21578;&#25991;&#23383;</th>
		          <th>&#38142;&#25509;</th>
		          <th>&#22270;&#29255;&#38142;&#25509;</th>
		          <th style="width:90px;">&#39068;&#33394;</th>
		          <th>&#24748;&#20572;&#25552;&#31034;</th>
		          <th style="width:82px;">&#25805;&#20316;</th>
		        </tr>
		      </thead>
		      <tbody id="adRows" data-next-index="<?php echo count($ads);?>">
		      <?php foreach($ads as $i=>$ad){?>
		        <tr data-ad-row>
		          <td class="text-center">
		            <input type="hidden" name="ad_index[]" value="<?php echo $i;?>">
		            <input type="checkbox" name="ad_enabled[<?php echo $i;?>]" value="1" <?php echo !empty($ad['enabled'])?'checked':null;?>>
		          </td>
		          <td>
		            <select class="form-control" name="ad_mode[<?php echo $i;?>]">
		              <option value="text" <?php echo (!isset($ad['mode']) || $ad['mode'] !== 'image')?'selected':null;?>>&nbsp;&#25991;&#23383;&#24191;&#21578;</option>
		              <option value="image" <?php echo (isset($ad['mode']) && $ad['mode'] === 'image')?'selected':null;?>>&nbsp;&#22270;&#29255;&#24191;&#21578;</option>
		            </select>
		          </td>
		          <td><input type="text" class="form-control" name="ad_text[<?php echo $i;?>]" value="<?php echo mpimg_admin_form_h($ad['text']);?>"></td>
		          <td><input type="text" class="form-control" name="ad_href[<?php echo $i;?>]" value="<?php echo mpimg_admin_form_h($ad['href']);?>"></td>
		          <td><input type="text" class="form-control script-image-input" name="ad_image[<?php echo $i;?>]" value="<?php echo mpimg_admin_form_h(isset($ad['image']) ? $ad['image'] : '');?>"></td>
		          <td><input type="color" class="form-control" name="ad_color[<?php echo $i;?>]" value="<?php echo mpimg_admin_h($ad['bgColor']);?>"></td>
		          <td><input type="text" class="form-control" name="ad_tooltip[<?php echo $i;?>]" value="<?php echo mpimg_admin_form_h($ad['tooltip']);?>"></td>
		          <td><button type="button" class="btn btn-danger btn-xs remove-ad-row"><i class="fa fa-trash"></i> &#21024;&#38500;</button></td>
		        </tr>
		      <?php }?>
		      </tbody>
		    </table>
		  </div>
		  <div class="script-help">&#24191;&#21578;&#20301;&#25968;&#37327;&#19981;&#22266;&#23450;&#12290;&#38656;&#35201;&#20960;&#20010;&#23601;&#28155;&#21152;&#20960;&#34892;&#65292;&#21024;&#38500;&#21518;&#20445;&#23384;&#21363;&#21487;&#31227;&#38500;&#12290;</div>
	    </div>
	  </div>

	  <div class="panel panel-info script-settings-panel script-panel-reward">
	    <div class="panel-heading"><h3 class="panel-title">&#25991;&#20214;&#39029;&#25195;&#30721;&#21306;&#22359;</h3></div>
	    <div class="panel-body">
		  <div class="form-group">
		    <label class="col-sm-2 control-label">&#24320;&#20851;</label>
		    <div class="col-sm-10">
		      <select class="form-control" name="file_reward_enable">
		        <option value="1" <?php echo $file_reward_enable?'selected':null;?>>&#24320;&#21551;</option>
		        <option value="0" <?php echo !$file_reward_enable?'selected':null;?>>&#20851;&#38381;</option>
		      </select>
		    </div>
		  </div>
		  <div class="form-group">
		    <label class="col-sm-2 control-label">&#26631;&#39064;</label>
		    <div class="col-sm-10">
		      <input type="text" class="form-control" name="file_reward_title" value="<?php echo mpimg_admin_form_h($file_reward_title);?>">
		    </div>
		  </div>
		  <div class="form-group">
		    <label class="col-sm-2 control-label">&#22270;&#29255;&#38142;&#25509;</label>
		    <div class="col-sm-10">
		      <input type="text" class="form-control" name="file_reward_image" value="<?php echo mpimg_admin_form_h($file_reward_image);?>">
		    </div>
		  </div>
		  <div class="script-help">&#29992;&#20110; file.php &#21491;&#20391;&#30340;&#25195;&#30721;&#22270;&#29255;&#21306;&#22359;&#12290;&#20851;&#38381;&#21518;&#21069;&#21488;&#19981;&#26174;&#31034;&#12290;</div>
	    </div>
	  </div>

	  <div class="panel panel-default script-settings-panel script-panel-action">
	    <div class="panel-body script-action-bar">
	      <a class="btn btn-default" href="./">&#36820;&#22238;&#21518;&#21488;&#39318;&#39029;</a>
	      <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> &#20445;&#23384;&#35774;&#32622;</button>
	    </div>
	  </div>
	</form>
  </div>
</div>
<script>
(function(){
	var tbody = document.getElementById('adRows');
	var addButton = document.getElementById('addAdRow');
	if(!tbody || !addButton)return;

	function nextIndex(){
		var index = parseInt(tbody.getAttribute('data-next-index') || '0', 10);
		tbody.setAttribute('data-next-index', index + 1);
		return index;
	}

	function emptyRow(){
		if(tbody.querySelector('[data-ad-row]'))return;
		var row = document.createElement('tr');
		row.className = 'script-empty-row';
		row.setAttribute('data-empty-row', '1');
		row.innerHTML = '<td colspan="8">&#26242;&#26080;&#24191;&#21578;&#20301;&#65292;&#28857;&#20987;&#21491;&#19978;&#35282;&ldquo;&#28155;&#21152;&#24191;&#21578;&rdquo;&#21019;&#24314;&#12290;</td>';
		tbody.appendChild(row);
	}

	function clearEmptyRow(){
		var row = tbody.querySelector('[data-empty-row]');
		if(row)row.parentNode.removeChild(row);
	}

	function addRow(){
		clearEmptyRow();
		var index = nextIndex();
		var row = document.createElement('tr');
		row.setAttribute('data-ad-row', '1');
		row.innerHTML =
			'<td class="text-center">' +
				'<input type="hidden" name="ad_index[]" value="' + index + '">' +
				'<input type="checkbox" name="ad_enabled[' + index + ']" value="1" checked>' +
			'</td>' +
			'<td><select class="form-control" name="ad_mode[' + index + ']"><option value="text" selected>&#25991;&#23383;&#24191;&#21578;</option><option value="image">&#22270;&#29255;&#24191;&#21578;</option></select></td>' +
			'<td><input type="text" class="form-control" name="ad_text[' + index + ']" value="&#24191;&#21578;&#25307;&#31199;"></td>' +
			'<td><input type="text" class="form-control" name="ad_href[' + index + ']" value="#"></td>' +
			'<td><input type="text" class="form-control script-image-input" name="ad_image[' + index + ']" value=""></td>' +
			'<td><input type="color" class="form-control" name="ad_color[' + index + ']" value="#2f86ff"></td>' +
			'<td><input type="text" class="form-control" name="ad_tooltip[' + index + ']" value="&#28857;&#20987;&#26597;&#30475;&#24191;&#21578;&#35814;&#24773;&#65292;&#27426;&#36814;&#21672;&#35810;&#12290;"></td>' +
			'<td><button type="button" class="btn btn-danger btn-xs remove-ad-row"><i class="fa fa-trash"></i> &#21024;&#38500;</button></td>';
		tbody.appendChild(row);
	}

	addButton.addEventListener('click', addRow);
	tbody.addEventListener('click', function(event){
		var target = event.target;
		while(target && target !== tbody && !target.classList.contains('remove-ad-row')){
			target = target.parentNode;
		}
		if(!target || target === tbody)return;
		var row = target;
		while(row && row !== tbody && !row.hasAttribute('data-ad-row')){
			row = row.parentNode;
		}
		if(row && row.parentNode){
			row.parentNode.removeChild(row);
			emptyRow();
		}
	});
	emptyRow();
})();
</script>
</body>
</html>

