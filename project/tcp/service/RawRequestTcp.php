<?php
/**
 * Author: Drunk (drunkce.com; idrunk.net)
 * Date: 2020/4/28 22:08
 */

namespace tcp\service;

use dce\Dce;
use dce\log\LogManager;
use dce\project\request\Request;
use dce\service\server\RawRequestConnection;
use dce\service\server\ServerMatrix;

class RawRequestTcp extends RawRequestConnection {
    public string $method = 'tcp';

    private array $raw;

    public function __construct(
        private ServerMatrix $server,
        string $data, int $fd, int $reactor_id,
    ) {
        $this->fd = $fd;
        $this->raw = [
            'fd' => $fd,
            'reactor_id' => $reactor_id,
            'data' => $data,
        ];
    }

    /** @inheritDoc */
    public function getServer(): ServerMatrix {
        return  $this->server;
    }

    /** @inheritDoc */
    public function getRaw(): array {
        return $this->raw;
    }

    /** @inheritDoc */
    public function init(): void {
        ['path' => $this->path, 'requestId' => $this->requestId, 'data' => $this->rawData, 'dataParsed' => $this->dataParsed] = $this->unPack($this->raw['data']);
    }

    /** @inheritDoc */
    public function supplementRequest(Request $request): void {
        $request->fd = $this->fd;
        $request->rawData = $this->rawData;
        if (is_array($this->dataParsed)) {
            $request->request = $this->dataParsed;
        }
        // 从var缓存取连接建立时实例化的Session对象
        $request->session = Dce::$cache->var->get(['session', $request->fd]);
    }

    /** @inheritDoc */
    public function response(mixed $data, string|false $path): bool {
        LogManager::response($this, $data);
        return $this->server->send($this->raw['fd'], $data, $path . (isset($this->requestId) ? self::REQUEST_SEPARATOR . $this->requestId : ''));
    }
}
