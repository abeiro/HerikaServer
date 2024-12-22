<?php
use Ratchet\App;
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
require __DIR__ . '/vendor/autoload.php';

class websocket implements MessageComponentInterface {
    protected $clients;

    public function __construct() {
        $this->clients = new \SplObjectStorage;
    }

    public function onOpen(ConnectionInterface $conn) {
        $this->clients->attach($conn);
        $conn->send(json_encode(['type' => 'status', 'data' => 'Connected to server']));
    }

    public function onMessage(ConnectionInterface $from, $msg) {
        $data = json_decode($msg, true);
        if (!$data) return;

        if ($data['type'] === 'ping') {
            $from->send(json_encode(['type' => 'pong']));
            return;
        }

        if ($data['type'] === 'response') {
            foreach ($this->clients as $client) {
                if ($client !== $from) {
                    $client->send(json_encode(['type' => 'latest_response', 'data' => $data['data']]));
                }
            }
        }

        if ($data['type'] === 'input') {
            foreach ($this->clients as $client) {
                if ($client !== $from) {
                    $client->send(json_encode(['type' => 'input', 'text' => $data['data']]));
                }
            }
        }
    }

    public function onClose(ConnectionInterface $conn) {
        $this->clients->detach($conn);
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        $conn->close();
    }
}

$app = new App('localhost', 43443, '0.0.0.0');
$app->route('/chat', new websocket, ['*']);
$app->run();
