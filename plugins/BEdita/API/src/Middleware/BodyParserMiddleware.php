<?php
declare(strict_types=1);
/**
 * BEdita, API-first content management framework
 * Copyright 2022 ChannelWeb Srl, Chialab Srl
 *
 * This file is part of BEdita: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * See LICENSE.LGPL or <http://gnu.org/licenses/lgpl-3.0.html> for more details.
 */

namespace BEdita\API\Middleware;

use Cake\Http\Middleware\BodyParserMiddleware as CakeBodyParserMiddleware;
use Closure;

/**
 * BodyParser middleware
 */
class BodyParserMiddleware extends CakeBodyParserMiddleware
{
    /**
     * {@inheritDoc}
     *
     * Add JSON API content type parser
     */
    public function __construct(array $options = [])
    {
        parent::__construct($options);
        $options += ['json' => true, 'form' => true];
        if ($options['json']) {
            $this->addParser(
                ['application/vnd.api+json'],
                Closure::fromCallable([$this, 'decodeJson'])
            );
        }
        if ($options['form']) {
            $this->addParser(
                ['application/x-www-form-urlencoded'],
                Closure::fromCallable([$this, 'decodeForm'])
            );
        }
    }

    /**
     * Decode `nto an array.
     *
     * @param string $body The request body to decode
     * @return array
     */
    protected function decodeForm(string $body): array
    {
        if ($body === '') {
            return [];
        }
        $result = [];
        parse_str(urldecode($body), $result);

        return $result;
    }

    /**
     * Decode JSON into an array.
     * Check for empty JSON objects in 'attributes' and avoid
     *
     * @param string $body The request body to decode
     * @return array|null
     */
    protected function decodeJson(string $body): ?array
    {
        $decoded = parent::decodeJson($body);
        if (empty($decoded) || empty($decoded['data']['attributes'])) {
            return $decoded;
        }

        // Decodes JSON without converting to associative array and
        // check if attributes having an empty array value are empty objects instead
        $object = json_decode($body);
        $attributes = $object->data->attributes ?? new \stdClass();
        foreach ($decoded['data']['attributes'] as $key => $value) {
            $item = $attributes->$key ?? null;
            if ($value === [] && $item !== []) {
                $decoded['data']['attributes'][$key] = $item;
            }
        }

        return $decoded;
    }
}
