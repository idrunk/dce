<?php
/**
 * Author: Drunk
 * Date: 2020-04-28 19:38
 */

namespace tcp\controller;

use dce\config\ConfigException;
use dce\project\Controller;
use dce\project\request\Request;
use rpc\dce\service\RpcServerApi;
use tcp\service\TcpServer;

class TcpServerController extends Controller {
    private TcpServer $server;

    public function __init(): void {
        $serverClass = $this->request->config->tcp['service'];
        if (! is_a($serverClass, TcpServer::class, true)) {
            throw new ConfigException('websocket.service配置非有效WebsocketService类');
        }
        // 构造函数内会挂载RPC客户端, 所以整个公共的呗
        $this->server = new $serverClass();
    }

    public function start() {
        $this->server->start($this->request->pureCli);
    }

    public function stop() {
        RpcServerApi::stop();
        $this->print('Tcp server was stopped.');
    }

    public function reload() {
        RpcServerApi::reload();
        $this->print('Tcp server was reloaded.');
    }

    public function status() {
        $status = RpcServerApi::status();
        $status = json_encode($status, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        $this->print($status);
    }
}
