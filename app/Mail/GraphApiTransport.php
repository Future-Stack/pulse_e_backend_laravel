<?php

namespace App\Mail;

use GuzzleHttp\Client;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\MessageConverter;

class GraphApiTransport extends AbstractTransport
{
    protected string $tenantId;
    protected string $clientId;
    protected string $clientSecret;
    protected string $fromEmail;

    public function __construct(string $tenantId, string $clientId, string $clientSecret, string $fromEmail)
    {
        parent::__construct();
        $this->tenantId = $tenantId;
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        $this->fromEmail = $fromEmail;
    }

    protected function doSend(SentMessage $message): void
    {
        $email = MessageConverter::toEmail($message->getOriginalMessage());

        $accessToken = $this->getAccessToken();

        $payload = $this->buildGraphPayload($email);

        $client = new Client();
        $client->post(
            "https://graph.microsoft.com/v1.0/users/{$this->fromEmail}/sendMail",
            [
                'headers' => [
                    'Authorization' => "Bearer {$accessToken}",
                    'Content-Type'  => 'application/json',
                ],
                'json' => $payload,
            ]
        );
    }

    protected function getAccessToken(): string
    {
        $client = new Client();

        $response = $client->post(
            "https://login.microsoftonline.com/{$this->tenantId}/oauth2/v2.0/token",
            [
                'form_params' => [
                    'client_id'     => $this->clientId,
                    'client_secret' => $this->clientSecret,
                    'scope'         => 'https://graph.microsoft.com/.default',
                    'grant_type'    => 'client_credentials',
                ],
            ]
        );

        $data = json_decode($response->getBody()->getContents(), true);

        return $data['access_token'];
    }

    protected function buildGraphPayload(Email $email): array
    {
        $toRecipients = [];
        foreach ($email->getTo() as $to) {
            $toRecipients[] = ['emailAddress' => ['address' => $to->getAddress()]];
        }

        $htmlBody = $email->getHtmlBody() ?? $email->getTextBody();

        $attachments = [];
        foreach ($email->getAttachments() as $attachment) {
            $headers = $attachment->getPreparedHeaders();
            $filename = $headers->getHeaderParameter('Content-Disposition', 'filename') ?? 'attachment';

            $attachments[] = [
                '@odata.type'  => '#microsoft.graph.fileAttachment',
                'name'         => $filename,
                'contentBytes' => base64_encode($attachment->getBody()),
            ];
        }

        $message = [
            'subject' => $email->getSubject(),
            'body' => [
                'contentType' => 'HTML',
                'content'     => $htmlBody,
            ],
            'toRecipients' => $toRecipients,
        ];

        if (!empty($attachments)) {
            $message['attachments'] = $attachments;
        }

        return [
            'message'         => $message,
            'saveToSentItems' => true,
        ];
    }

    public function __toString(): string
    {
        return 'graph-api';
    }
}