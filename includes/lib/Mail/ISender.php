<?php
namespace lib\Mail;

/*
 * 发信驱动接口。
 *
 * 后台可以同时勾选多个通道，Mailer 会按固定顺序挨个试，第一个成功就停；
 * 所以每个驱动都要如实返回失败原因，否则出问题时根本不知道卡在哪一环。
 */
interface ISender
{
	//通道名字，用在后台的测试结果里
	function name();

	//参数配置齐了没有，没配齐的通道直接跳过，不占用重试次数
	function isReady();

	/*
	 * 发一封信。
	 * 返回 ['ok'=>bool, 'msg'=>string]，msg 在失败时要带上对方的原始响应，
	 * 只回一句“发送失败”对排查没有任何帮助。
	 */
	function send($to, $subject, $html, $text);
}
