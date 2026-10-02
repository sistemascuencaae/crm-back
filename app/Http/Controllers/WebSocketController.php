<?php
// app/Http/Controllers/WebSocketController.php

namespace App\Http\Controllers;

use BeyondCode\LaravelWebSockets\Contracts\ChannelManager as ContractsChannelManager;
use BeyondCode\LaravelWebSockets\WebSockets\Channels\ChannelManager;
use Illuminate\Support\Facades\Cache;

class WebSocketController
{
    public function getActiveChannelsCount()
    {
        $channelManager = app(ContractsChannelManager::class);

        // Obtener la lista de canales activos
        $activeChannels = $channelManager->getChannels()->toArray();

        // Contar el número de canales activos
        $activeChannelsCount = count($activeChannels);

        return response()->json(['active_channels_count' => $activeChannelsCount]);
    }
}
