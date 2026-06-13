<?php

namespace App\Services\Calendar;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use Microsoft\Graph\Graph;
use Microsoft\Graph\Model;

class Office365CalendarService
{
    protected string $clientId;

    protected string $tenantId;

    protected string $clientSecret;

    protected string $userId;

    protected string $accessToken = '';

    public function __construct()
    {
        $this->clientId = config('services.microsoft.client_id') ?? '';
        $this->tenantId = config('services.microsoft.tenant_id') ?? '';
        $this->clientSecret = config('services.microsoft.client_secret') ?? '';
        $this->userId = config('services.microsoft.user_id') ?? '';
    }

    /**
     * Fetch all events from the user's primary Office 365 calendar.
     */
    public function getEvents(): array
    {
        if (empty($this->clientId) || empty($this->tenantId) || empty($this->clientSecret) || empty($this->userId)) {
            return [];
        }

        try {
            $token = $this->getAccessToken();

            $graph = new Graph;
            $graph->setAccessToken($token);

            // Fetch events for the user
            $events = $graph->createRequest('GET', "/users/{$this->userId}/calendar/events")
                ->setReturnType(Model\Event::class)
                ->execute();

            $normalizedEvents = [];
            foreach ($events as $event) {
                $normalizedEvents[] = [
                    'id' => $event->getId(),
                    'title' => $event->getSubject(),
                    'start' => $event->getStart()->getDateTime(),
                    'end' => $event->getEnd()->getDateTime(),
                    'provider' => 'office365',
                ];
            }

            return $normalizedEvents;

        } catch (\Exception $e) {
            Log::error('Office 365 Sync Error: '.$e->getMessage());

            return [];
        }
    }

    /**
     * Get an access token using Client Credentials flow (App-only access).
     */
    protected function getAccessToken(): string
    {
        if ($this->accessToken) {
            return $this->accessToken;
        }

        $guzzle = new Client;
        $url = "https://login.microsoftonline.com/{$this->tenantId}/oauth2/v2.0/token";

        $response = $guzzle->post($url, [
            'form_params' => [
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'scope' => 'https://graph.microsoft.com/.default',
                'grant_type' => 'client_credentials',
            ],
        ]);

        $data = json_decode($response->getBody()->getContents(), true);
        $this->accessToken = $data['access_token'];

        return $this->accessToken;
    }
}
