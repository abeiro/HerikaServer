<?php
use Ratchet\App;
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use React\EventLoop\Loop;
require __DIR__ . '/vendor/autoload.php';

class websocket implements MessageComponentInterface {
    protected $clients;
    private $db_conn;

    public function __construct() {
        $this->clients = new \SplObjectStorage;
        $this->connectDB();
        // Start polling for new messages in the input queue
        $this->startPolling();
    }

    private function connectDB() {
        $host = 'localhost';
        $port = '5432';
        $dbname = 'dwemer';
        $username = 'dwemer';
        $password = 'dwemer';

        $this->db_conn = pg_connect("host=$host port=$port dbname=$dbname user=$username password=$password");

        if (!$this->db_conn) {
            error_log("WebSocket server failed to connect to the database!");
        }
    }

    private function startPolling() {
        // Poll every 1 second (adjust as needed)
        echo "starting polling".PHP_EOL;
        $rate_limit=1;
        Loop::addPeriodicTimer($rate_limit, function () {
            $this->checkInputQueue();
        });
    }

    private function checkInputQueue() {
        if (!$this->db_conn) {
            $this->connectDB();
            if (!$this->db_conn) {
                return;
            }
        }
    
        $query = "SELECT id, message FROM input_queue_websocket ORDER BY timestamp ASC";
        $result = pg_query($this->db_conn, $query);
    
        if ($result) {
            while ($row = pg_fetch_assoc($result)) {
                $messageData = json_decode($row['message'], true);
                if ($messageData) {
                    // Send the message to all connected clients (e.g., Chrome extension)
                    foreach ($this->clients as $client) {
                        // We broadcast the original array as JSON-encoded text for the extension
                        $client->send(json_encode([
                            'type' => 'input',
                            'text' => json_encode($messageData)
                        ]));
                    }
                }
                // Delete the processed message from the input queue
                pg_query($this->db_conn, "DELETE FROM input_queue_websocket WHERE id = {$row['id']}");
            }
        }
    }

    public function onOpen(ConnectionInterface $conn) {
        $this->clients->attach($conn);
        $conn->send(json_encode(['type' => 'status', 'data' => 'Connected to WebSocket server']));
    }

    public function onMessage(ConnectionInterface $from, $msg) {
        $data = json_decode($msg, true);
        if (!$data) return;

        if ($data['type'] === 'ping') {
            $from->send(json_encode(['type' => 'pong']));
            return;
        }

        // When the Chrome extension sends a response, store it in the output queue
        if ($data['type'] === 'response') {
            if (!$this->db_conn) {
                $this->connectDB();
                if (!$this->db_conn) {
                    return;
                }
            }
            // CHANGED: Instead of json_encode, just store plain text in the DB
            $escapedResponse = pg_escape_string($this->db_conn, $data['data']);
            $query = "INSERT INTO output_queue_websocket (response) VALUES ('$escapedResponse')";
            pg_query($this->db_conn, $query);
        }

        // Keep the existing logic for direct client-to-client messaging if needed
        if ($data['type'] === 'input') {
            foreach ($this->clients as $client) {
                if ($client !== $from) {
                    $client->send(json_encode([
                        'type' => 'input',
                        'text' => $data['data']
                    ]));
                }
            }
        }
    }

    public function onClose(ConnectionInterface $conn) {
        $this->clients->detach($conn);
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        error_log("WebSocket error: {$e->getMessage()}");
        $conn->close();
    }
}

$app = new App('localhost', 43443, '0.0.0.0');
$app->route('/chat', new websocket, ['*']);
$app->run();
