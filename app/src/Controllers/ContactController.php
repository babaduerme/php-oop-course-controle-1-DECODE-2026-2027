<?php

namespace App\Controllers;

use App\lib\Controllers\AbstractController;
use App\lib\Http\Request;
use App\lib\Http\Response;
use App\Services\ContactStorage;
use App\Services\ContactValidationException;
use App\Services\ContactValidator;
use JsonException;
use Throwable;

final class ContactController extends AbstractController
{
    public function process(Request $request): Response
    {
        if ($request->getMethod() === 'GET') {
            $filename = $request->getRouteParam('filename');
            if ($filename !== null) {
                try {
                    $contact = (new ContactStorage())->findByFilename($filename);
                } catch (Throwable) {
                    return $this->jsonResponse(500, ['error' => 'The contact could not be read.']);
                }

                if ($contact === null) {
                    return $this->jsonResponse(404, ['error' => 'Contact not found.']);
                }

                return $this->jsonResponse(200, $contact);
            }

            try {
                $contacts = (new ContactStorage())->findAll();
            } catch (Throwable) {
                return $this->jsonResponse(500, ['error' => 'The contacts could not be read.']);
            }

            return $this->jsonResponse(200, $contacts);
        }

        if ($request->getMethod() === 'PATCH') {
            if (!$this->hasJsonContentType($request->getHeaders())) {
                return $this->jsonResponse(415, ['error' => 'Content-Type must be application/json.']);
            }

            $filename = $request->getRouteParam('filename');
            if ($filename === null) {
                return $this->jsonResponse(400, ['error' => 'A contact filename is required.']);
            }

            $storage = new ContactStorage();
            try {
                if ($storage->findByFilename($filename) === null) {
                    return $this->jsonResponse(404, ['error' => 'Contact not found.']);
                }
            } catch (Throwable) {
                return $this->jsonResponse(500, ['error' => 'The contact could not be read.']);
            }

            try {
                $changes = (new ContactValidator())->validateUpdate($request->getBody());
            } catch (ContactValidationException $exception) {
                return $this->jsonResponse(400, ['error' => $exception->getMessage()]);
            }

            try {
                $contact = $storage->updateByFilename($filename, $changes);
            } catch (Throwable) {
                return $this->jsonResponse(500, ['error' => 'The contact could not be updated.']);
            }

            if ($contact === null) {
                return $this->jsonResponse(404, ['error' => 'Contact not found.']);
            }

            return $this->jsonResponse(200, $contact);
        }

        if (!$this->hasJsonContentType($request->getHeaders())) {
            return $this->jsonResponse(415, ['error' => 'Content-Type must be application/json.']);
        }

        try {
            $contact = (new ContactValidator())->validate($request->getBody());
        } catch (ContactValidationException $exception) {
            return $this->jsonResponse(400, ['error' => $exception->getMessage()]);
        }

        try {
            $filename = (new ContactStorage())->save($contact);
        } catch (Throwable) {
            return $this->jsonResponse(500, ['error' => 'The contact could not be saved.']);
        }

        return $this->jsonResponse(201, ['file' => $filename]);
    }

    private function hasJsonContentType(array $headers): bool
    {
        foreach ($headers as $name => $value) {
            if (strcasecmp((string) $name, 'Content-Type') !== 0 || !is_string($value)) {
                continue;
            }

            $mediaType = strtolower(trim(explode(';', $value, 2)[0]));

            return $mediaType === 'application/json';
        }

        return false;
    }

    private function jsonResponse(int $statusCode, array $data): Response
    {
        $response = new Response();
        $response->statusCode = $statusCode;
        $response->headers = ['Content-Type' => 'application/json'];

        try {
            $response->content = json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        } catch (JsonException) {
            $response->statusCode = 500;
            $response->content = '{"error":"Unable to create response."}';
        }

        return $response;
    }
}
