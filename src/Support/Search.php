<?php

namespace Lodestone\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * The LIKE search behind a table's search box.
 *
 * @internal
 */
class Search
{
    /**
     * Constrain the query to rows where any of the attributes contains the term.
     *
     * Dotted names search relations with whereHas. With $matchKey, "389" or "#389" also finds the
     * record with that primary key.
     *
     * @param  list<string>  $attributes
     */
    public static function apply(Builder $query, array $attributes, string $term, bool $matchKey = false): void
    {
        // A typed "%" or "_" is a character to match, not a wildcard.
        $like = '%'.addcslashes($term, '%_\\').'%';

        $query->where(function (Builder $query) use ($attributes, $term, $like, $matchKey) {
            foreach ($attributes as $name) {
                // A TagsColumn path such as 'tenants.*.student.name' searches the relation without the wildcard.
                $name = str_replace('.*', '', $name);

                if (str_contains($name, '.')) {
                    $query->orWhereHas(Str::beforeLast($name, '.'), fn (Builder $related) => $related->where($related->qualifyColumn(Str::afterLast($name, '.')), 'like', $like));
                } else {
                    $query->orWhere($query->qualifyColumn($name), 'like', $like);
                }
            }

            if ($matchKey && ctype_digit($id = ltrim($term, '#'))) {
                $query->orWhere($query->getModel()->getQualifiedKeyName(), $id);
            }
        });
    }
}
