<?php

namespace App\Services;

use JsonException;

final class ContactValidator
{
    private const array REQUIRED_FIELDS = ['email', 'subject', 'message'];

    public function validate(string $body): array
    {
        try {
            $decoded = json_decode($body, false, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new ContactValidationException('Request body must contain valid JSON.');
        }

        if (!$decoded instanceof \stdClass) {
            throw new ContactValidationException('Request body must be a JSON object.');
        }

        $contact = get_object_vars($decoded);
        $unknownFields = array_diff(array_keys($contact), self::REQUIRED_FIELDS);
        if ($unknownFields !== []) {
            throw new ContactValidationException('Only email, subject and message properties are allowed.');
        }

        foreach (self::REQUIRED_FIELDS as $field) {
            if (!array_key_exists($field, $contact)) {
                throw new ContactValidationException(sprintf('The %s property is required.', $field));
            }
            if (!is_string($contact[$field])) {
                throw new ContactValidationException(sprintf('The %s property must be a string.', $field));
            }

            $contact[$field] = trim($contact[$field]);
            if ($contact[$field] === '') {
                throw new ContactValidationException(sprintf('The %s property must not be empty.', $field));
            }
        }

        if (filter_var($contact['email'], FILTER_VALIDATE_EMAIL) === false) {
            throw new ContactValidationException('The email property must contain a valid email address.');
        }

        return $contact;
    }
}
