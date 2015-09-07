<?php

namespace Gini\Controller\CGI\Gapper;

class Client extends \Gini\Controller\CGI\Gapper
{
    use \Gini\Module\Gapper\Client\RPCTrait;

    private function _checkUrl($base, $to)
    {
        if (empty($base) || empty($to)) {
            return false;
        }
        $base = parse_url($base);
        $to = parse_url($to);
        if ($base['host'] != $to['host']) {
            return false;
        }

        return true;
    }

    public function actionGoHome()
    {
        $paths = func_get_args();

        $config = (array) \Gini\Config::get('gapper.rpc');
        $client_id = $config['client_id'];
        if (!$client_id) {
            return \Gini\IoC::construct('\Gini\CGI\Response\Nothing');
        }

        $url = \Gini\Config::get('gapper.server_home') ?: 'http://gapper.in/';

        if (empty($paths)) {
            $group_id = \Gini\Gapper\Client::getGroupID();
            if ($group_id) {
                $url .= '/dashboard/group/'.$group_id;
            }
        } else {
            $url .= '/'.implode('/', $paths);
        }

        $user = \Gini\Gapper\Client::getUserInfo();
        if ($user['id']) {
            $token = self::getRPC()->gapper->user->getLoginToken((int) $user['id'], $client_id);
        }

        if ($token) {
            $url = \Gini\URI::url($url, 'gapper-token='.$token);
        } else {
            $url = \Gini\URI::url($url);
        }

        return $this->redirect($url);
    }

    public function actionGo($client_id)
    {
        if (\Gini\Gapper\Client::getLoginStep() !== \Gini\Gapper\Client::STEP_DONE) {
            return \Gini\IoC::construct('\Gini\CGI\Response\Nothing');
        }

        $redirect = $_GET['redirect'];

        if (!$client_id) {
            return \Gini\IoC::construct('\Gini\CGI\Response\Nothing');
        }
        $app = self::getRPC()->gapper->app->getInfo($client_id);
        if (!$app['id']) {
            return \Gini\IoC::construct('\Gini\CGI\Response\Nothing');
        }
        $user = \Gini\Gapper\Client::getUserInfo();
        if (!$user['id']) {
            return \Gini\IoC::construct('\Gini\CGI\Response\Nothing');
        }
        $token = self::getRPC()->gapper->user->getLoginToken((int) $user['id'], $client_id);
        if (!$token) {
            return \Gini\IoC::construct('\Gini\CGI\Response\Nothing');
        }
        $url = $app['url'];
        if ($this->_checkUrl($url, $redirect)) {
            $url = $redirect;
        }

        $url = \Gini\URI::url($url, 'gapper-token='.$token);

        return $this->redirect($url);
    }

    public function actionLogin()
    {
        $redirect = $_GET['redirect'];
        if (\Gini\Gapper\Client::getLoginStep() === \Gini\Gapper\Client::STEP_DONE) {
            $redirect = $this->_checkUrl('/', $redirect) ? $redirect : '/';

            return $this->redirect($redirect);
        }

        $view = \Gini\Config::get('gapper.login_view') ?: 'gapper/client/login';
        $this->view->body = VV($view);
    }
    public function actionNoAccount(){
        $config = (array) \Gini\Config::get('gapper.rpc');
        $client_id = $config['client_id'];
        if (!$client_id) {
            return \Gini\IoC::construct('\Gini\CGI\Response\Nothing');
        }
        $app = self::getRPC()->gapper->app->getInfo($client_id);
        if($app['type']=='group'){
             $view = \Gini\Config::get('gapper.group_account_view') ?: 'gapper/client/group_account';
             parent::setJSVar('ACTION', 'group_account');
             $this->view->body = VV($view);
        }else if($app['type']=='user'){
            $view = \Gini\Config::get('gapper.user_account_view') ?: 'gapper/client/user_account';
            parent::setJSVar('ACTION', 'user_account');
            $this->view->body = VV($view);
        }
    }
}
