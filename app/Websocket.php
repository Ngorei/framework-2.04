<?php
namespace app;
use app\tatiye;
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
use React\EventLoop\LoopInterface;

class Websocket implements MessageComponentInterface {
    protected $clients;
    protected $subscriptions = [];
    protected $loop;

    public function __construct(LoopInterface $loop = null) {
        $this->clients = new \SplObjectStorage;
        $this->loop = $loop;
    }
    
    public function run() {
        $server = IoServer::factory(
            new HttpServer(
                new WsServer($this)
            ),
            8080
        );
        echo "Server SDK berjalan di PORT:8080\n";
        $server->run();
    }

    public function onOpen(ConnectionInterface $conn) {
        $this->clients->attach($conn);
        echo "Koneksi baru! ({$conn->resourceId})\n";
    }

    public function onMessage(ConnectionInterface $from, $msg) {
        try {
            $data = json_decode($msg, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception("Format JSON tidak valid");
            }

            switch($data['type']) {
                case 'apiRequest':
                    $this->handleApiRequest($from, $data);
                    break;
                    
                case 'subscribe':
                    $this->handleSubscription($from, $data);
                    break;
                    
                case 'select':
                    $this->handleSelect($from, $data);
                    break;
                    
                default:
                    $response = [
                        'type' => 'message',
                        'content' => $msg
                    ];
                    $from->send(json_encode($response));
            }
        } catch (\Exception $e) {
            $response = [
                'type' => 'error',
                'message' => $e->getMessage()
            ];
            $from->send(json_encode($response));
            
            error_log('WebSocket Error: ' . $e->getMessage());
        }
    }

    public function onClose(ConnectionInterface $conn) {
        $this->clients->detach($conn);
        
        foreach ($this->subscriptions as $endpoint => $subscribers) {
            if ($subscribers->contains($conn)) {
                $subscribers->detach($conn);
                if ($subscribers->count() === 0) {
                    unset($this->subscriptions[$endpoint]);
                }
            }
        }
        
        echo "Koneksi {$conn->resourceId} telah terputus\n";
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        echo "Terjadi error: {$e->getMessage()}\n";
        $conn->close();
    }



    protected function handleApiRequest($client, $request) {
        try {
            if (!isset($request['payload'])) {
                throw new \Exception('Payload tidak boleh kosong');
            }

            if (!isset($request['endpoint']) || empty($request['endpoint'])) {
                throw new \Exception('Endpoint tidak boleh kosong');
            }

            $payload = is_string($request['payload']) ? 
                      $request['payload'] : 
                      json_encode($request['payload']);

            $vid = isset($request['vid']) ? $request['vid'] : '';

            $dataMonitor = tatiye::StorageBuckets([
                'endpoint' => $request['endpoint'],
                'vid'      => $vid,
                'payload'  => $payload
            ]);
            
            $response = [
                'type' => 'apiResponse',
                'status' => 'success',
                'data' => [
                    'payload' => $dataMonitor,
                    'timestamp' => date('Y-m-d H:i:s')
                ]
            ];

            $client->send(json_encode($response));

            $this->broadcastUpdate($request['endpoint'], $dataMonitor);

        } catch (\Exception $e) {
            $response = [
                'type' => 'apiError',
                'status' => 'error',
                'message' => $e->getMessage()
            ];
            $client->send(json_encode($response));
            error_log('WebSocket Error: ' . $e->getMessage());
        }
    }

    protected function handleSubscription($client, $data) {
        if ($client === null) {
            return [
                'type' => 'subscribed',
                'status' => 'success',
                'data' => [
                    'endpoint' => $data['endpoint'],
                    'message' => 'Successfully subscribed to updates'
                ]
            ];
        }
        
        if (!isset($this->subscriptions[$data['endpoint']])) {
            $this->subscriptions[$data['endpoint']] = new \SplObjectStorage;
        }
        $this->subscriptions[$data['endpoint']]->attach($client);
        
        $response = [
            'type' => 'subscribed',
            'status' => 'success',
            'data' => [
                'endpoint' => $data['endpoint'],
                'message' => 'Successfully subscribed to updates'
            ]
        ];
        $client->send(json_encode($response));
    }

    protected function handleSelect($client, $data) {
        try {
            if (!isset($data['endpoint']) || empty($data['endpoint'])) {
                throw new \Exception('Endpoint tidak boleh kosong');
            }

            $result = tatiye::StorageBuckets([
                'endpoint' => $data['endpoint'],
                'action'   => 'getBuckets',
                'payload'  => json_encode(['action' => 'getBuckets'])
            ]);
            
            $response = [
                'type' => 'select',
                'status' => 'success',
                'data' => [
                    'response' =>' $result ?: []',
                ]
            ];
            
            $client->send(json_encode($response));

        } catch (\Exception $e) {
            $response = [
                'type' => 'error',
                'status' => 'error',
                'message' => $e->getMessage()
            ];
            $client->send(json_encode($response));
            error_log('Select Error: ' . $e->getMessage());
        }
    }

    protected function broadcastUpdate($endpoint, $data) {
        if (isset($this->subscriptions[$endpoint])) {
            $update = [
                'type' => 'update',
                'endpoint' => $endpoint,
                'data' => $data,
                'timestamp' => date('Y-m-d H:i:s')
            ];
            
            $updateJson = json_encode($update);
            
            foreach ($this->subscriptions[$endpoint] as $subscriber) {
                try {
                    $subscriber->send($updateJson);
                } catch (\Exception $e) {
                    error_log("Error broadcasting to subscriber: " . $e->getMessage());
                    $this->subscriptions[$endpoint]->detach($subscriber);
                }
            }
        }
    }

    protected function broadcast($message) {
        foreach ($this->clients as $client) {
            $client->send($message);
        }
    }

    public function Rtdb($endpoint, $data) {
        try {
            $request = json_decode($data, true);
            
            $dataMonitor = tatiye::StorageBuckets([
                'endpoint' => $endpoint,
                'payload' => $request['payload'],
                'vid' => $request['vid'] ?? '',
                'timestamp' => date('Y-m-d H:i:s')
            ]);

            $this->broadcastUpdate($endpoint, $dataMonitor);

            return [
                'status' => 'success',
                'data' => $dataMonitor
            ];

        } catch (\Exception $e) {
            error_log('Rtdb Error: ' . $e->getMessage());
            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }
}