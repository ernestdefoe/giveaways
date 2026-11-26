<?php

namespace ErnestDefoe\Giveaways\Tests\integration\api;

/**
 * Giveaways and categories are JSON:API resources. The tests read them as the
 * forum's api.ts does: each resource flattened to its attributes with an
 * integer `id`, everything else (meta, errors, the entrants list) as it is.
 */
trait FlattensJsonApi
{
    protected function flat(?array $body): ?array
    {
        if (! is_array($body) || ! array_key_exists('data', $body)) {
            return $body;
        }

        $one = fn ($r) => is_array($r) && isset($r['type'], $r['attributes'])
            ? ['id' => is_numeric($r['id']) ? (int) $r['id'] : $r['id']] + $r['attributes']
            : $r;

        $body['data'] = is_array($body['data']) && array_is_list($body['data'])
            ? array_map($one, $body['data'])
            : $one($body['data']);

        return $body;
    }

    /** The JSON:API body for a create, update or action: the type, the id on an update. */
    protected function document(string $method, string $path, array $attributes): array
    {
        $type = str_contains($path, 'giveaway-categories') ? 'giveaway-categories' : 'giveaways';
        $data = ['type' => $type, 'attributes' => $attributes];
        if ($method === 'PATCH' && preg_match('#/(\d+)$#', $path, $m)) {
            $data['id'] = $m[1];
        }

        return ['data' => $data];
    }
}
