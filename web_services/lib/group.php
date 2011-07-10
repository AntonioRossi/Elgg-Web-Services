<?php
/**
 * Elgg Webservices plugin 
 * 
 * @package Webservice
 * @author Saket Saurabh
 *
 */
 
 /**
 * Web service for joining a group
 *
 * @param string $username username of author
 * @param string $groupid  GUID of the group
 *
 * @return bool
 */
function rest_group_join($username, $groupid, $password) {
	$user = get_user_by_username($username);
	if (!$user) {
		throw new InvalidParameterException('registration:usernamenotvalid');
	}
	
	$group = get_entity($groupid);
	
	if (($user instanceof ElggUser) && ($group instanceof ElggGroup)) {
		// join or request
		$join = false;
		if ($group->isPublicMembership() || $group->canEdit($user->guid)) {
			// anyone can join public groups and admins can join any group
			$join = true;
		} else {
			if (check_entity_relationship($group->guid, 'invited', $user->guid)) {
				// user has invite to closed group
				$join = true;
			}
		}

		if ($join) {
			if (groups_join_group($group, $user)) {
				return elgg_echo("groups:joined");
			} else {
				return elgg_echo("groups:cantjoin");
			}
		} else {
			add_entity_relationship($user->guid, 'membership_request', $group->guid);

			// Notify group owner
			$url = "{$CONFIG->url}mod/groups/membershipreq.php?group_guid={$group->guid}";
			$subject = elgg_echo('groups:request:subject', array(
				$user->name,
				$group->name,
			));
			$body = elgg_echo('groups:request:body', array(
				$group->getOwnerEntity()->name,
				$user->name,
				$group->name,
				$user->getURL(),
				$url,
			));
			if (notify_user($group->owner_guid, $user->getGUID(), $subject, $body)) {
				return elgg_echo("groups:joinrequestmade");
			} else {
				return elgg_echo("groups:joinrequestnotmade");
			}
		}
	} else {
		return elgg_echo("groups:cantjoin");
	}
} 
				
expose_function('group.join',
				"rest_group_join",
				array('username' => array ('type' => 'string'),
						'groupid' => array ('type' => 'string'),
					),
				"Join a group",
				'GET',
				true,
				false);
				
 /**
 * Web service for leaving a group
 *
 * @param string $username username of author
 * @param string $groupid  GUID of the group
 *
 * @return bool
 */
function rest_group_leave($username, $groupid) {
	$user = get_user_by_username($username);
	if (!$user) {
		throw new InvalidParameterException('registration:usernamenotvalid');
	}
	
	$group = get_entity($groupid);
	
	set_page_owner($group->guid);
	if (($user instanceof ElggUser) && ($group instanceof ElggGroup)) {
		if ($group->getOwnerGUID() != elgg_get_logged_in_user_guid()) {
			if ($group->leave($user)) {
				return elgg_echo("groups:left");
			} else {
				return elgg_echo("groups:cantleave");
			}
		} else {
			return elgg_echo("groups:cantleave");
		}
	} else {
		return elgg_echo("groups:cantleave");
	}
} 
				
expose_function('group.leave',
				"rest_group_leave",
				array('username' => array ('type' => 'string'),
						'groupid' => array ('type' => 'string'),
					),
				"leave a group",
				'GET',
				true,
				false);
				
 /**
 * Web service for posting a new topic to a group
 *
 * @param string $username       username of author
 * @param string $groupid        GUID of the group
 * @param string $title          Title of new topic
 * @param string $description    Content of the post
 * @param string $status         status of the post
 * @param string $access_id      Access ID of the post
 *
 * @return bool
 */
function rest_group_post($username, $groupid, $title, $desc, $tags = "", $status = "published", $access_id = ACCESS_DEFAULT) {
	$user = get_user_by_username($username);
	if (!$user) {
		throw new InvalidParameterException('registration:usernamenotvalid');
	}
	$group = get_entity($groupid);
	if (!$group) {
		return elgg_echo('group:notfound');
	}

	// make sure user has permissions to write to container
	if (!can_write_to_container($user->guid, $groupid, "all", "all")) {
		return elgg_echo('groups:permissions:error');
	}
	
	$topic = new ElggObject();
	$topic->subtype = 'groupforumtopic';
	$topic->owner_guid = $user->guid;
	$topic->title = $title;
	$topic->description = $desc;
	$topic->status = $status;
	$topic->access_id = $access_id;
	$topic->container_guid = $groupid;
			
	$tags = explode(",", $tags);
	$topic->tags = $tags;

	$result = $topic->save();

	if (!$result) {
		return elgg_echo('discussion:error:notsaved');
	}
	return elgg_echo('discussion:topic:created');
} 
				
expose_function('group.post',
				"rest_group_post",
				array('username' => array ('type' => 'string'),
						'groupid' => array ('type' => 'int'),
						'title' => array ('type' => 'string'),
						'desc' => array ('type' => 'string'),
						'tags' => array ('type' => 'string', 'required' => false),
						'status' => array ('type' => 'string', 'required' => false),
						'access_id' => array ('type' => 'int', 'required' => false),
					),
				"Post to a group",
				'POST',
				true,
				false);
				
/**
 * Web service for posting a new topic to a group
 *
 * @param string $username username of author
 * @param string $topicid  Topic ID
 *
 * @return bool
 */
function rest_group_deletepost($username, $topicid) {
	$topic = get_entity($topicid);
	if (!$topic || !$topic->getSubtype() == "groupforumtopic") {
		return elgg_echo('discussion:error:notdeleted');
	}

	$user = get_user_by_username($username);
	if (!$user) {
		return elgg_echo('registration:usernamenotvalid');
	}

	if (!$topic->canEdit($user->guid)) {
		return elgg_echo('discussion:error:permissions');
	}

	$container = $topic->getContainerEntity();

	$result = $topic->delete();
	if ($result) {
		return elgg_echo('discussion:topic:deleted');
	} else {
		return elgg_echo('discussion:error:notdeleted');
	}
} 
				
expose_function('group.deletepost',
				"rest_group_deletepost",
				array('username' => array ('type' => 'string'),
						'topicid' => array ('type' => 'int'),
					),
				"Post to a group",
				'POST',
				true,
				false);
				
/**
 * Web service get latest post in a group
 *
 * @param string $groupid GUID of the group
 * @param string $limit   (optional) default 10
 * @param string $offset  (optional) default 0
 *
 * @return bool
 */
function rest_group_latest($groupid, $limit = 10, $offset = 0) {
	$group = get_entity($groupid);
	if (!$group) {
		return elgg_echo('group:notfound');
	}
	
	$options = array(
		'type' => 'object',
		'subtype' => 'groupforumtopic',
		'container_guid' => $groupid,
		'limit' => $limit,
		'offset' => $offset,
		'full_view' => false,
		'pagination' => false,
	);
	$content = elgg_get_entities($options);
	if($content) {
		foreach($content as $single ) {
			$post[$single->guid]['title'] = $single->title;
			$post[$single->guid]['description'] = $single->description;
			$post[$single->guid]['owner_guid'] = $single->owner_guid;
			$post[$single->guid]['container_guid'] = $single->container_guid;
			$post[$single->guid]['access_id'] = $single->access_id;
			$post[$single->guid]['time_created'] = $single->time_created;
			$post[$single->guid]['time_updated'] = $single->time_updated;
			$post[$single->guid]['last_action'] = $single->last_action;
		}
	}
	else {
		$post = elgg_echo('discussion:topic:notfound');
	}
	return $post;
} 
				
expose_function('group.latest',
				"rest_group_latest",
				array('groupid' => array ('type' => 'string'),
					  'limit' => array ('type' => 'int', 'required' => false),
					  'offset' => array ('type' => 'int', 'required' => false),
					),
				"Get posts from a group",
				'GET',
				true,
				false);

/**
 * Web service get replies on a post
 *
 * @param string $postid GUID of the group
 * @param string $limit   (optional) default 10
 * @param string $offset  (optional) default 0
 *
 * @return bool
 */
function rest_group_getreplies($postid, $limit = 10, $offset = 0) {
	$group = get_entity($postid);
	$options = array(
		'guid' => $postid,
		'annotation_name' => 'group_topic_post',
		'limit' => $limit,
		'offset' => $offset,
	);
	$content = elgg_get_annotations($options);
	if($content) {
		foreach($content as $single ) {
			$post[$single->id]['value'] = $single->value;
			$post[$single->id]['name'] = $single->name;
			$post[$single->id]['enabled'] = $single->enabled;
			$post[$single->id]['owner_guid'] = $single->owner_guid;
			$post[$single->id]['entity_guid'] = $single->entity_guid;
			$post[$single->id]['access_id'] = $single->access_id;
			$post[$single->id]['time_created'] = $single->time_created;
			$post[$single->id]['name_id'] = $single->name_id;
			$post[$single->id]['value_id'] = $single->value_id;
			$post[$single->id]['value_type'] = $single->value_type;
		}
	}
	else {
		$post = elgg_echo('discussion:reply:noreplies');
	}
	return $post;
} 
				
expose_function('group.getreplies',
				"rest_group_getreplies",
				array('postid' => array ('type' => 'string'),
					  'limit' => array ('type' => 'int', 'required' => false),
					  'offset' => array ('type' => 'int', 'required' => false),
					),
				"Get posts from a group",
				'GET',
				true,
				false);
				
/**
 * Web service post a reply
 *
 * @param string $username username
 * @param string $postid   GUID of post
 * @param string $text     text of reply
 *
 * @return bool
 */
function rest_group_reply($username, $postid, $text) {
	$entity_guid = (int) get_input('entity_guid');

	if (empty($text)) {
		return elgg_echo('grouppost:nopost');
	}

	$topic = get_entity($postid);
	if (!$topic) {
		return elgg_echo('grouppost:nopost');
	}

	$user = get_user_by_username($username);
	if (!$user) {
		return elgg_echo('registration:usernamenotvalid');
	}

	$group = $topic->getContainerEntity();
	if (!$group->canWriteToContainer($user)) {
		return elgg_echo('groups:notmember');
	}

	$reply_id = $topic->annotate('group_topic_post', $text, $topic->access_id, $user->guid);
	if ($reply_id == false) {
		return elgg_echo('groupspost:failure');
	}
	
	add_to_river('river/annotation/group_topic_post/reply', 'reply', $user->guid, $topic->guid, "", 0, $reply_id);
	return true;
} 
				
expose_function('group.reply',
				"rest_group_reply",
				array('username' => array ('type' => 'string'),
						'postid' => array ('type' => 'string'),
						'text' => array ('type' => 'string'),
					),
				"Post a reply to a group",
				'POST',
				true,
				false);
				
/**
 * Web service post a reply
 *
 * @param string $username username
 * @param string $id       Annotation ID of reply
 *
 * @return bool
 */
function rest_group_deletereply($username, $id) {
	$reply = elgg_get_annotation_from_id($id);
	if (!$reply || $reply->name != 'group_topic_post') {
		return elgg_echo('discussion:reply:error:notdeleted');
	}
	
	$user = get_user_by_username($username);
	if (!$user) {
		return elgg_echo('registration:usernamenotvalid');
	}

	if (!$reply->canEdit($user->guid)) {
		return elgg_echo('discussion:error:permissions');
	}

	$result = $reply->delete();
	if ($result) {
		return elgg_echo('discussion:reply:deleted');
	} else {
		return elgg_echo('discussion:reply:error:notdeleted');
	}
} 
				
expose_function('group.deletereply',
				"rest_group_deletereply",
				array('username' => array ('type' => 'string'),
						'id' => array ('type' => 'string'),
					),
				"Delete a reply from a group",
				'POST',
				true,
				false);