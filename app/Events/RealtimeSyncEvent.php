<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannels;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

class RealtimeSyncEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets;

    /**
     * The channel name to broadcast on.
     *
     * @var string
     */
    public $channel;

    /**
     * The event name.
     *
     * @var string
     */
    public $eventName;

    /**
     * The event data.
     *
     * @var array
     */
    public $data;

    /**
     * Create a new event instance.
     *
     * @param string $channel
     * @param string $eventName
     * @param array $data
     * @return void
     */
    public function __construct(string $channel, string $eventName, array $data)
    {
        $this->channel = $channel;
        $this->eventName = $eventName;
        $this->data = $data;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn()
    {
        return [
            new PrivateChannel($this->channel),
        ];
    }

    /**
     * The event's broadcast name.
     *
     * @return string
     */
    public function broadcastAs()
    {
        return $this->eventName;
    }

    /**
     * Get the data to broadcast.
     *
     * @return array
     */
    public function broadcastWith()
    {
        return $this->data;
    }
}