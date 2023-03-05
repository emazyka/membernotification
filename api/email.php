<?php

if (!defined('VB_ENTRY')) {
	die('Access denied.');
}

class MemberNotification_Api_Email extends vB_Api
{
	private $post;
	private $notificationSubject;
	private $notificationMessage;

	public function notifyUsers($nodeId, $threadUrl)
	{
		$this->setData($nodeId, $threadUrl);
		$this->sendNotifications();
	}

	private function setData($nodeId, $threadUrl)
	{
		$user = self::getUser();

		$post = self::getPost($nodeId, $threadUrl);

		$this->post = [
			'type' => $post['type'],
			'firstName' => $user['firstName'],
			'lastName' => $user['lastName'],
			'title' => $post['title'],
			'channelId' => $post['channelId'],
			'channelTitle' => $post['channelTitle'],
			'channelParentTitle' => $post['channelParentTitle'],
			'threadLink' => $post['threadLink'],
			'postContent' => $post['postContent'],
		];
	}

	private static function getUser()
	{
		$api = vB_Api::instance('user');
		$userId = vB::getCurrentSession()->get('userid');
		$user = $api->fetchUserinfo($userId);

		if (isset($user['errors'])) {
			return;
		}

		$firstName = $user['field6'];
		$lastName = $user['field7'];

		return [
			'firstName' => $firstName,
			'lastName' => $lastName,
		];
	}

	private static function getPost($nodeId, $threadUrl)
	{
		$node = vB_Library::instance('node')->getNodeBare($nodeId);

		$postContent = $node['description'];
		$type = $nodeId == $node['starter'] ? 'thread' : 'post';

		$threadId = $node['parentid'];
		$thread = vB_Library::instance('node')->getNodeBare($threadId);

		$title = $type == 'post' ? $thread['title'] : $node['title'];

		$channelId = $thread['parentid'];
		$channel = vB_Library::instance('node')->getNodeBare($channelId);
		$channelTitle = $channel['title'];
		$parentChannelId = $channel['parentid'];
		$parentChannel = vB_Library::instance('node')->getNodeBare($parentChannelId);
		$channelParentTitle = $parentChannel['title'];
		$extra = ['p' => $nodeId];
		$anchor = 'post' . $nodeId;
		$nodeRouteId = $node['routeid'];
		$url = vB5_Route::buildUrl($nodeRouteId . '|fullurl', ['nodeid' => $nodeId], $extra, $anchor);
		$threadLink = $type == 'thread' ? $threadUrl : $url;

		return [
			'title' => $title,
			'postContent' => $postContent,
			'type' => $type,
			'channelId' => $channelId,
			'channelTitle' => $channelTitle,
			'channelParentTitle' => $channelParentTitle,
			'threadLink' => $threadLink
		];
	}

	private function sendNotifications()
	{
		$keep_keys = array_flip(['email']);
		$users = $this->getUsers();
		$notifications = $this->getNotifications($users);
		$this->sendMails($notifications);
	}

	private function sendMails($notifications)
	{
		try {
			vB_Mail::vbmailStart();
			foreach ($notifications as $notification) {
				self::log($notification['email'], 'membernotifications-mail.log');
				vB_Mail::vbmail2($notification['email'], $notification['subject'], $notification['message']);
			}
			vB_Mail::vbmailEnd();
		} catch (\Exception $error) {
			self::log($error, 'membernotifications-error.log');
		}
	}

	private function getNotifications($users)
	{
		$this->notificationSubject = "New Forum Topic: " . $this->post['title'];

		if ($this->post['type'] == 'post') {
			$this->notificationSubject = $this->post['firstName'] . " " . $this->post['lastName'] . " posted in Thread: " . $this->post['title'];
		}

		$this->notificationMessage = $this->createNotificationTemplate();

		$this->logTemplate();

		$notifications = [];

		foreach ($users as $user) {
			$notifications[] = [
				'email' => $user['email'],
				'subject' => $this->notificationSubject,
				'message' => $this->notificationMessage
			];
		}

		return $notifications;
	}

	private function getUsers()
	{
		$users = [];

		$vbulletin = &vB::get_registry();
		$assertor = vB::getDbAssertor();
		$keepKeys = array_flip(['userid', 'email']);

		$sql = "SELECT * FROM " . TABLE_PREFIX . "user";
		$results = $vbulletin->db->query_read_slave($sql);

		if ($results) {
			while ($user = $vbulletin->db->fetch_array($results)) {
				$userId = $user['userid'];
				$userContext = vB::getUserContext($userId);
				$hasCanViewPermission = $userContext->getChannelPermission('forumpermissions', 'canview', $this->post['channelId']);

				if ($hasCanViewPermission) {
					$userFields = $assertor->getRow('vBForum:userfield', ['userid' => $userId]);
					$user = array_merge($user, $userFields);
					$user = array_intersect_key($user, $keepKeys);
					$users[] = $user;
				}
			}
		}

		return $users;
	}

	private function createNotificationTemplate()
	{
		$filename = __DIR__ . '/../templates/template-' . $this->post['type'] . '.html';
		$notificationTemplate = file_get_contents($filename);

		$notificationData = [
			'first_name' => $this->post['firstName'],
			'last_name' => $this->post['lastName'],
			'thread_name' => $this->post['title'],
			'channel_name' => $this->post['channelTitle'],
			'channel_parent_name' => $this->post['channelParentTitle'],
			'thread_link' => $this->post['threadLink'],
			'posts' => $this->post['postContent'],
		];

		foreach ($notificationData as $key => $value) {
			$notificationTemplate = str_replace('{' . $key . '}', $value, $notificationTemplate);
		}

		return $notificationTemplate;
	}

	private function logTemplate()
	{
		$testFilePath = __DIR__ . '/../test/' . $this->post['type'] . '.html';
		file_put_contents($testFilePath, $this->notificationMessage);
	}

	private static function log($data, $logFile = 'membernotifications.log')
	{
		$logData = json_encode($data, JSON_PRETTY_PRINT) . "\n";
		file_put_contents($logFile, $logData, FILE_APPEND);
	}
}
