<?php

public function create()
{
    $webhook = fixer::input('post')
        ->add('createdBy', $this->app->user->account)
        ->add('createdDate', helper::now())
        ->join('products', ',')
        ->join('executions', ',')
        ->skipSpecial('url')
        ->trim('agentId,appKey,appSecret,wechatAgentId,wechatCorpId,wechatCorpSecret,feishuAppId,feishuAppSecret,mmAgentId,mmToken')
        ->remove('allParams, allActions')
        ->get();
    $webhook->params = $this->post->params ? implode(',', $this->post->params) . ',text' : 'text';

    if($webhook->type == 'dinguser')
    {
        $webhook->secret = array();
        $webhook->secret['agentId']   = $webhook->agentId;
        $webhook->secret['appKey']    = $webhook->appKey;
        $webhook->secret['appSecret'] = $webhook->appSecret;

        if(empty($webhook->agentId))   dao::$errors['agentId']   = sprintf($this->lang->error->notempty, $this->lang->webhook->dingAgentId);
        if(empty($webhook->appKey))    dao::$errors['appKey']    = sprintf($this->lang->error->notempty, $this->lang->webhook->dingAppKey);
        if(empty($webhook->appSecret)) dao::$errors['appSecret'] = sprintf($this->lang->error->notempty, $this->lang->webhook->dingAppSecret);
        if(dao::isError()) return false;

        $webhook->secret = json_encode($webhook->secret);
        $webhook->url    = $this->config->webhook->dingapiUrl;
    }
    elseif($webhook->type == 'wechatuser')
    {
        $webhook->secret = array();
        $webhook->secret['agentId']   = $webhook->wechatAgentId;
        $webhook->secret['appKey']    = $webhook->wechatCorpId;
        $webhook->secret['appSecret'] = $webhook->wechatCorpSecret;

        if(empty($webhook->wechatCorpId))     dao::$errors['wechatCorpId']     = sprintf($this->lang->error->notempty, $this->lang->webhook->wechatCorpId);
        if(empty($webhook->wechatCorpSecret)) dao::$errors['wechatCorpSecret'] = sprintf($this->lang->error->notempty, $this->lang->webhook->wechatCorpSecret);
        if(empty($webhook->wechatAgentId))    dao::$errors['wechatAgentId']    = sprintf($this->lang->error->notempty, $this->lang->webhook->wechatAgentId);
        if(dao::isError()) return false;

        $webhook->secret = json_encode($webhook->secret);
        $webhook->url    = $this->config->webhook->wechatApiUrl;
    }
    elseif($webhook->type == 'feishuuser')
    {
        $webhook->secret = array();
        $webhook->secret['appId']     = $webhook->feishuAppId;
        $webhook->secret['appSecret'] = $webhook->feishuAppSecret;

        if(empty($webhook->feishuAppId))     dao::$errors['feishuAppId']     = sprintf($this->lang->error->notempty, $this->lang->webhook->feishuAppId);
        if(empty($webhook->feishuAppSecret)) dao::$errors['feishuAppSecret'] = sprintf($this->lang->error->notempty, $this->lang->webhook->feishuAppSecret);
        if(dao::isError()) return false;

        $webhook->secret = json_encode($webhook->secret);
        $webhook->url    = $this->config->webhook->feishuApiUrl;
    }
    elseif($webhook->type == 'mmuser')
    {
        $webhook->secret = array();
        $webhook->secret['agentId']   = $webhook->mmAgentId;
        $webhook->secret['appSecret'] = $webhook->mmToken;
        $webhook->secret = json_encode($webhook->secret);
    }

    unset($webhook->mmAgentId);
    unset($webhook->mmToken);
    $this->dao->insert(TABLE_WEBHOOK)->data($webhook, 'agentId,appKey,appSecret,wechatCorpId,wechatCorpSecret,wechatAgentId,feishuAppId,feishuAppSecret')
        ->batchCheck($this->config->webhook->create->requiredFields, 'notempty')
        ->autoCheck()
        ->exec();
    return $this->dao->lastInsertId();
}