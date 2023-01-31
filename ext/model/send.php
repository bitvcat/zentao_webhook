<?php
public function buildRtxData($objectType, $objectID, $actionType, $actionID, $webhook)
{
    if(!isset($this->lang->action->label)) $this->loadModel('action');
    if(!isset($this->lang->action->label->$actionType)) return false;
    if(empty($this->config->objectTables[$objectType])) return false;
    $action = $this->dao->select('*')->from(TABLE_ACTION)->where('id')->eq($actionID)->fetch();

    if($webhook->products)
    {
        $webhookProducts = explode(',', trim($webhook->products, ','));
        $actionProduct   = explode(',', trim($action->product, ','));
        $intersect       = array_intersect($webhookProducts, $actionProduct);
        if(!$intersect) return false;
    }
    if($webhook->executions)
    {
        if(strpos(",$webhook->executions,", ",$action->execution,") === false) return false;
    }

    static $users = array();
    if(empty($users)) $users = $this->loadModel('user')->getList();

    $object = $this->dao->select('*')->from($this->config->objectTables[$objectType])->where('id')->eq($objectID)->fetch();
    if(!in_array($objectType, $this->config->webhook->needAssignTypes) || empty($object->assignedTo))
    {
        return false;
    }

    // sendTo 兼容
    $rtxUser = $object->assignedTo;
    $userStatusUrl = $webhook->url . "/getstatus.php?username={$rtxUser}";
    $userStatus = trim(common::http($userStatusUrl));
    print("userStatus = {$userStatus}");
    if ($userStatus != "1" && $userStatus != "0") {
        // 去除工号，例如: zhangsan123 -> zhangsan
        preg_match('/\d+$/', $object->assignedTo, $matches);
        if(!empty($matches)){
            $rtxUser = rtrim($object->assignedTo, $matches[0]);
            $userStatusUrl = $webhook->url . "/getstatus.php?username={$rtxUser}";
            $userStatus = trim(common::http($userStatusUrl));
        }else{
            return false;
        }
    }
    if ($userStatus != "1" && $userStatus != "0") {
        return false;
    }

    $field          = $this->config->action->objectNameFields[$objectType];
    $host           = empty($webhook->domain) ? common::getSysURL() : $webhook->domain;
    $viewLink       = $this->getViewLink($objectType, $objectID);
    $objectTypeName = ($objectType == 'story' and $object->type == 'requirement') ? $this->lang->action->objectTypes['requirement'] : $this->lang->action->objectTypes[$objectType];
    $title          = $this->app->user->realname . $this->lang->action->label->$actionType . $objectTypeName;
    //$text           = $title . ' ' . "[#{$objectID}::{$object->$field}](" . $host . $viewLink . ")";
    $link = "[#{$objectID}::{$object->$field}|{$host}{$viewLink}]";
    $text = rawurlencode(iconv('UTF-8', 'GBK//TRANSLIT', $title) . '  ' . iconv('UTF-8', 'GBK//TRANSLIT', $link));

    $url = $webhook->url;
    $url .= "/sendnotify.cgi?" . "&receiver={$rtxUser}&msg={$text}&title=".iconv('UTF-8', 'GBK//TRANSLIT', "禅道通知");
    //$gbkUrl = helper::convertEncoding($url, "utf-8", 'gbk');
    return $url;
}

public function postToMatterMost($objectType, $objectID, $actionType, $actionID, $webhook)
{
    if(!isset($this->lang->action->label)) $this->loadModel('action');
    if(!isset($this->lang->action->label->$actionType)) return false;
    if(empty($this->config->objectTables[$objectType])) return false;
    $action = $this->dao->select('*')->from(TABLE_ACTION)->where('id')->eq($actionID)->fetch();

    if($webhook->products)
    {
        $webhookProducts = explode(',', trim($webhook->products, ','));
        $actionProduct   = explode(',', trim($action->product, ','));
        $intersect       = array_intersect($webhookProducts, $actionProduct);
        if(!$intersect) return false;
    }
    if($webhook->executions)
    {
        if(strpos(",$webhook->executions,", ",$action->execution,") === false) return false;
    }

    $object = $this->dao->select('*')->from($this->config->objectTables[$objectType])->where('id')->eq($objectID)->fetch();
    if (empty($object->assignedTo)) {
        return false;
    }

    if(is_string($webhook->secret)) $webhook->secret = json_decode($webhook->secret);

    // headers
    $headers = array(
        'Content-Type: application/json',
        'Authorization: Bearer '.$webhook->secret->appSecret,
    );

    // 1. 通过用户名获取用户id
    $url =  $webhook->url . "/api/v4/users/usernames";
    $mmUser = $object->assignedTo;
    $names = array($mmUser);

    // 去除工号
    preg_match('/\d+$/', $object->assignedTo, $matches);
    if(!empty($matches)){
        $mmUser = rtrim($object->assignedTo, $matches[0]);
        array_push($names, $mmUser);
    }
    $response = common::http($url, json_encode($names), null, $headers);
    $response = json_decode($response);
    if(empty($response)) {
        return false;
    }
    $mmUser = $response[0];

    // 2. 建立direct channel
    $url =  $webhook->url . "/api/v4/channels/direct";
    $userIds = array($webhook->secret->agentId, $mmUser->id);
    $response = common::http($url, json_encode($userIds), null, $headers);
    $response = json_decode($response);

    // 3. 向 channel 发送消息
    $field    = $this->config->action->objectNameFields[$objectType];
    $host     = empty($webhook->domain) ? common::getSysURL() : $webhook->domain;
    $viewLink = $this->getViewLink($objectType, $objectID);
    $title    = $this->app->user->realname . $this->lang->action->label->$actionType . $this->lang->action->objectTypes[$objectType];
    $text     = $title . ' ' . "[#{$objectID}::{$object->$field}](" . $host . $viewLink . ")";

    $url =  $webhook->url . "/api/v4/posts";
    $postData = array();
    $postData['channel_id'] = $response->id;
    $postData['message'] = $text;
    $response = common::http($url, json_encode($postData), null, $headers);
    // $response = json_decode($response);
    // var_dump("--------->>>>");
    // var_dump($response);

    return $response;
}

public function send($objectType, $objectID, $actionType, $actionID, $actor = '')
{
    static $webhooks = array();
    if(!$webhooks) $webhooks = $this->getList();
    if(!$webhooks) return true;

    foreach($webhooks as $id => $webhook)
    {
        if($webhook->type == 'rtx'){
            //var_dump($webhook);
            //if(true) continue;
            $rtxUrl = $this->buildRtxData($objectType, $objectID, $actionType, $actionID, $webhook);
            if(!$rtxUrl) continue;

            $result = common::http($rtxUrl);
            if(!empty($result)) $this->saveLog($webhook, $actionID, $rtxUrl, $result);
        } elseif ($webhook->type == 'mmuser') {
            $result = $this->postToMatterMost($objectType, $objectID, $actionType, $actionID, $webhook);
            if(!empty($result)) $this->saveLog($webhook, $actionID, $postData, $result);
        } else {
            $postData = $this->buildData($objectType, $objectID, $actionType, $actionID, $webhook);
            if(!$postData) continue;

            if($webhook->sendType == 'async')
            {
                if($webhook->type == 'dinguser')
                {
                    $openIdList = $this->getOpenIdList($webhook->id, $actionID);
                    if(empty($openIdList)) continue;
                }

                $this->saveData($id, $actionID, $postData, $actor);
                continue;
            }

            $result = $this->fetchHook($webhook, $postData, $actionID);
            if(!empty($result)) $this->saveLog($webhook, $actionID, $postData, $result);
        }
    }
    return !dao::isError();
}
