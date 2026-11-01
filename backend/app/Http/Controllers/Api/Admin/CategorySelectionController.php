<?php
namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;

class CategorySelectionController extends Controller
{
    public function __invoke()
    {
        $categories = Category::query()->select(['id','name','name_ar','parent_id','slug'])
            ->orderBy('sort_order')->orderBy('id')->get();
        $byParent = $categories->groupBy(fn ($c) => $c->parent_id === null ? 'root' : (string) $c->parent_id);
        $seen = [];
        $tree = function ($parent, int $depth = 0) use (&$tree, &$seen, $byParent) {
            $result = [];
            if ($depth > 60) return $result;
            foreach ($byParent->get($parent === null ? 'root' : (string) $parent, collect()) as $c) {
                if (isset($seen[$c->id])) continue;
                $seen[$c->id] = true;
                $node = $c->toArray();
                $node['children'] = $tree((int) $c->id, $depth+1);
                $result[] = $node;
            }
            return $result;
        };
        $roots = $tree(null);
        foreach ($categories as $c) {
            if (!isset($seen[$c->id])) {
                $seen[$c->id] = true;
                $node = $c->toArray();
                $node['children'] = $tree((int) $c->id);
                $roots[] = $node;
            }
        }
        return response()->json(['categories'=>$roots,'total'=>$categories->count()]);
    }
}
