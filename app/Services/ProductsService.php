<?php

namespace App\Services;

use App\Models\UserCrawlerResult;
use App\Models\UserMeta;
use App\Models\UserProductRelations;
use App\Models\UserProducts;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProductsService
{
    public function getProducts(array $data): array
    {
        $offset = 0;
        $limit = 10;

        if (isset($data["data"]["index"])) {
            $offset = $data["data"]["index"];
        }
        if (isset($data["data"]["search"])) {
            $products = UserCrawlerResult::where(function ($query) use ($data) {
                $query->orWhere("sku", "like", "%" . $data["data"]["search"] . "%")
                    ->orWhere("mpn", "like", "%" . $data["data"]["search"] . "%")
                    ->orWhere("name", "like", "%" . $data["data"]["search"] . "%")
                    ->orWhere("ean", "like", "%" . $data["data"]["search"] . "%");
            })
                ->where("shop_id", "=", $data["data"]["cid"])
                ->where("user_id", "=", $this->guard()->user()->id);
//                var_dump($data["data"]["cid"]);
//                var_dump($products->toSql());die();
        } else {
            $products = UserCrawlerResult::where("shop_id", "=", $data["data"]["cid"]);
        }

        $counter = $products->count();
        $products = $products->offset($offset * $limit)->limit($limit)->get()->all();

        return ["status" => "ok", "data" => ["products" => $products, "counter" => $counter]];

    }

    public function getMatchedProducts(array $data): array
    {
        $offset = 0;
        $limit = 10;

        if (isset($data["data"]["index"])) {
            $offset = $data["data"]["index"];
        }
        $products = UserCrawlerResult::getRawFilteredProductsById($this->guard()->user()->id, $data["data"]["pid"], [], $offset, $limit);
        $counter = UserCrawlerResult::getRawFilteredProductsCountById($this->guard()->user()->id, $data["data"]["pid"], []);

        return ["status" => "ok", "data" => ["products" => $products, "counter" => $counter]];
    }

    public function setProduct(array $data): array
    {
        $model = UserCrawlerResult::where("id", "=", $data["data"]["product"])->first();
        $form = json_decode($data["data"]["form"]);
        if ($model != null) {
            if ($form->manualPrice != null) {
                if ($form->manualPrice == "true") {
                    $form->manualPrice = 1;
                } else {
                    $form->manualPrice = 0;
                }
            }
            $model->updateOrCreate(
                [
                    "id" => $model->id
                ],
                [
                    "name" => $form->title != null ? $form->title : $model->name,
                    "description" => $form->description != null ? $form->description : $model->description,
                    "category" => $form->category != null ? $form->category : $model->category,
                    "brand" => $form->brand != null ? $form->brand : $model->brand,
                    "sku" => $form->sku != null ? $form->sku : $model->sku,
                    "price" => $form->price != null ? $form->price : $model->price,
                    "ean" => $form->ean != null ? $form->ean : $model->ean,
                    "isbn" => $form->isbn != null ? $form->isbn : $model->isbn,
                    "asin" => $form->asin != null ? $form->asin : $model->asin,
                    "min_rsp" => $form->minPrice != null ? $form->minPrice : $model->min_rsp,
                    "max_rsp" => $form->maxPrice != null ? $form->maxPrice : $model->max_rsp,
                    "manual_rsp" => $form->manualPrice != null ? $form->manualPrice : $model->manual_rsp,
                    "sync" => false,
                    "old_price" => "",
                    "updated_at" => time()
                ]);
        }
        return ["status" => "ok"];
    }

    public function setProductRelations($user_id = null, $crawlerProductId = null, $webshopId = null)
    {
        if ($user_id != null) {
            if ($crawlerProductId != null) {
                $crawlerProduct = UserCrawlerResult::find($crawlerProductId);
            } else {
                $crawlerProduct = UserCrawlerResult::all();
            }
            if ($webshopId != null) {
                $crawlerProduct = UserCrawlerResult::where("shop_id", $webshopId)->get()->all();
            }
            if ($crawlerProductId == null) {
                foreach ($crawlerProduct as $cp) {
                    $productModel = UserProducts::where(function ($query) use ($cp) {
                        $query->orWhere('sku', "=", $cp->sku)
                            ->orWhere('gid', "=", $cp->gid)
                            ->orWhere('mpn', "=", $cp->mpn);
                    })->where("user_id", $user_id)->get()->all();
//                    $clear = UserProductRelations::where("user_crowler_product_id", $cp->id)->forceDelete();
                    foreach ($productModel as $product) {
                        $relationModel = new UserProductRelations();
                        $relationModel->updateOrCreate(
                            [
                                "user_crowler_product_id" => $cp->id,
                                "shop_id" => $cp->shop_id,
                                "user_product_id" => $product->id
                            ],
                            [
                                "user_crowler_product_id" => $cp->id,
                                "shop_id" => $cp->shop_id,
                                "user_product_id" => $product->id
                            ]);
//                        $relationModel->user_crowler_product_id = $cp->id;
//                        $relationModel->shop_id = $cp->shop_id;
//                        $relationModel->user_product_id = $product->id;
//                        $relationModel->save();
                    }
                }
            } else {
                $productModel = UserProducts::where(function ($query) use ($crawlerProduct) {
                    $query->orWhere('sku', "=", $crawlerProduct->sku)
                        ->orWhere('gid', "=", $crawlerProduct->gid)
                        ->orWhere('mpn', "=", $crawlerProduct->mpn);
                })->where("user_id", $user_id)->get();
//                $clear = UserProductRelations::where("user_crowler_product_id", $crawlerProduct->id)->forceDelete();
                foreach ($productModel as $product) {
                    $relationModel = new UserProductRelations();
                    $relationModel->updateOrCreate(
                        [
                            "user_crowler_product_id" => $crawlerProduct->id,
                            "shop_id" => $crawlerProduct->shop_id,
                            "user_product_id" => $product->id
                        ],
                        [
                            "user_crowler_product_id" => $crawlerProduct->id,
                            "shop_id" => $crawlerProduct->shop_id,
                            "user_product_id" => $product->id
                        ]);
//                    $relationModel = new UserProductRelations();
//                    $relationModel->user_crowler_product_id = $crawlerProduct->id;
//                    $relationModel->user_product_id = $product->id;
//                    $relationModel->shop_id = $crawlerProduct->shop_id;
//                    $relationModel->save();
                }
            }
        }
    }

    public function deleteUserProduct(array $data): array
    {
        $model = UserCrawlerResult::find($data["pid"]);
        $crawlerModel = new UserCrawlerResult();
        $result = $crawlerModel->updateOrCreate(
            [
                "id" => $model->id,
            ],
            [
                "sku" => $model->sku_old,
                "ean" => $model->ean_old,
                "mpn" => $model->mpn_old,
            ]
        );
        $model = UserProductRelations::where("user_crowler_product_id", "=", $data["pid"])->forceDelete();

        $model = UserMeta::where("user_id", Auth::id())->where("meta_name", "favorite_products");
        $olData = unserialize($model->first()->meta_value);
        if ($olData != null) {
            foreach ($olData as $key => $pid) {
                $openPid = explode("-", $pid);
                if ($openPid[0] == $data["ppid"] && $data["pid"] == $openPid[1]) {
                    unset($olData[$key]);
                }
            }
            $model = $model->updateOrCreate(
                [
                    "user_id" => Auth::id(),
                    "meta_name" => "favorite_products",
                ],
                [
                    "meta_value" => serialize(array_unique($olData)),
                    "updated_at" => time()
                ]);
        }
        $this->setProductRelations(Auth::id(), $data["pid"]);
        return ["status" => "ok", "data" => ["message" => "Crawler product deleted."]];
    }
}
