<?php
namespace lib;

/*
 * 发信总入口。
 *
 * 后台可以同时勾选多个通道，没有默认通道，一个都不勾就是关闭发信功能。
 * 发信时按固定顺序（SMTP → Resend → Brevo → SendGrid）挨个试，第一个成功就停；
 * 前面的失败了自动切到下一个勾选的通道，所以某家临时抽风不至于把注册流程卡死。
 *
 * 每次尝试的结果都记在 attempts 里，后台的测试发信按钮直接把它显示出来，
 * 省得出问题只看到一句“发送失败”。
 */
class Mailer
{
	//尝试顺序写死在这里：SMTP 一般是站长自己的邮箱，优先用它
	private static $order = ['smtp', 'resend', 'brevo', 'sendgrid'];

	private $attempts = [];

	/*
	 * 当前勾选并且参数配置完整的通道，返回 ['键名' => 驱动对象]
	 */
	public function senders()
	{
		global $conf;
		$from = isset($conf['mail_from']) ? trim($conf['mail_from']) : '';
		$from_name = isset($conf['mail_from_name']) ? trim($conf['mail_from_name']) : '';
		$list = [];

		foreach (self::$order as $key) {
			if (empty($conf['mail_' . $key . '_open'])) continue;
			if ($key === 'smtp') {
				$sender = new Mail\Smtp([
					'host' => isset($conf['mail_smtp_host']) ? $conf['mail_smtp_host'] : '',
					'port' => isset($conf['mail_smtp_port']) ? $conf['mail_smtp_port'] : 465,
					'secure' => isset($conf['mail_smtp_secure']) ? $conf['mail_smtp_secure'] : 'ssl',
					'user' => isset($conf['mail_smtp_user']) ? $conf['mail_smtp_user'] : '',
					'pass' => isset($conf['mail_smtp_pass']) ? $conf['mail_smtp_pass'] : '',
					'from' => $from,
					'from_name' => $from_name,
				]);
			} else {
				$sender = new Mail\Api($key, [
					'key' => isset($conf['mail_' . $key . '_key']) ? $conf['mail_' . $key . '_key'] : '',
					'from' => $from,
					'from_name' => $from_name,
				]);
			}
			$list[$key] = $sender;
		}
		return $list;
	}

	/*
	 * 发信功能是否可用：至少有一个勾选的通道参数是齐的
	 */
	public function isReady()
	{
		foreach ($this->senders() as $sender) {
			if ($sender->isReady()) return true;
		}
		return false;
	}

	/*
	 * 发一封信。返回 ['ok'=>bool, 'msg'=>string, 'sender'=>成功的通道名]
	 */
	public function send($to, $subject, $html, $text = '')
	{
		global $conf;
		$this->attempts = [];
		if ($text === '') $text = $this->htmlToText($html);

		$senders = $this->senders();
		if (!$senders) {
			return ['ok' => false, 'msg' => '后台没有勾选任何发信通道'];
		}

		foreach ($senders as $key => $sender) {
			if (!$sender->isReady()) {
				//参数没配齐的通道跳过，但要记下来，否则站长会以为它试过了
				$this->attempts[] = ['name' => $sender->name(), 'ok' => false, 'msg' => '参数没有配置完整，已跳过'];
				continue;
			}
			$res = $sender->send($to, $subject, $html, $text);
			$attempt = ['name' => $sender->name(), 'ok' => !empty($res['ok']), 'msg' => $res['msg']];
			if ($key === 'smtp' && empty($res['ok']) && method_exists($sender, 'sessionLog')) {
				$attempt['detail'] = $sender->sessionLog();
			}
			$this->attempts[] = $attempt;
			if (!empty($res['ok'])) {
				return ['ok' => true, 'msg' => '发送成功', 'sender' => $sender->name()];
			}
		}
		return ['ok' => false, 'msg' => '所有勾选的通道都发送失败'];
	}

	public function attempts()
	{
		return $this->attempts;
	}

	/*
	 * 纯文本版正文：不给纯文本版的话，部分邮箱会更容易判成垃圾邮件
	 */
	private function htmlToText($html)
	{
		$text = preg_replace('/<br\s*\/?>/i', "\n", $html);
		$text = preg_replace('/<\/(p|div|tr|h[1-6])>/i', "\n", $text);
		$text = strip_tags($text);
		$text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
		$text = preg_replace("/[ \t]+/", ' ', $text);
		$text = preg_replace("/\n{3,}/", "\n\n", $text);
		return trim($text);
	}
}
