<?php

namespace App\Http\Controllers;

use App\Jobs\ApplyPixelforgeEvent;
use App\Models\WebhookEvent;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PixelforgeWebhookController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $payload = $request->json()->all();
        $eventId = $payload['id'] ?? null;
        $eventType = $payload['type'] ?? null;

        if (! is_string($eventId) || $eventId === '' || ! is_string($eventType) || $eventType === '') {
            return response()->json(['message' => 'Malformed Pixelforge event.'], 400);
        }

        $event = WebhookEvent::createOrFirst(
            ['provider_event_id' => $eventId],
            [
                'type' => $eventType,
                'payload' => $payload,
                'headers' => array_filter([
                    'pixelforge-event-id' => $request->header('Pixelforge-Event-Id'),
                    'pixelforge-event-type' => $request->header('Pixelforge-Event-Type'),
                    'pixelforge-delivery-attempt' => $request->header('Pixelforge-Delivery-Attempt'),
                ]),
            ],
        );

        if ($event->wasRecentlyCreated) {
            dispatch(new ApplyPixelforgeEvent($event->id));
        }

        return response()->noContent();
    }
}
