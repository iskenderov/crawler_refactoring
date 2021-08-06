<?php

namespace App\Http\Controllers;

use App\Http\Models\UserCrawlerResult;
use App\Http\Models\UserProductRelations;
use App\Http\Models\UserProducts;
use App\Http\Models\UserWebshops;
use App\Http\Models\UserWebshopsUrls;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\User;
use App\Http\Models\UserMeta;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Goutte;
use phpDocumentor\Reflection\Types\Null_;
use WebScraper\ApiClient\Client;

class CrawlerController extends Controller
{
    protected $counterS = [];
    private $ch;

    public function __construct(Request $request = null)
    {
        $this->request = $request;

        $this->ch = curl_init();

        if (!isset ($opts ['timeout']) || !is_int($opts ['timeout'])) {
            $opts ['timeout'] = 10000;
        }

        curl_setopt($this->ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($this->ch, CURLOPT_USERAGENT, 'TS-PHP/1.0.0');
        curl_setopt($this->ch, CURLOPT_HEADER, false);
        curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->ch, CURLOPT_CONNECTTIMEOUT, 50000);
        curl_setopt($this->ch, CURLOPT_TIMEOUT, $opts ['timeout']);
//        $this->middleware('jwt.auth', ['except' => ['login']]);
    }

    public function crawler($user_id = null, $shop_id = null, $shopURLNext = null, $next = false)
    {
        $products = [];
        $output = [];
        $updated = [];
        $no_pageSize = false;
        if ($this->request != null) {
            $input = $this->request->all();
        }

        if (isset($input["uid"]) && isset($input["sid"])) {
            $user_id = $input["uid"];
            $shop_id = $input["sid"];
        }

        if ($user_id != null && $shop_id != null && $shop_id != 3) {
            $cc = new Goutte\Client();
            $shopURL = UserWebshops::where(["user_id" => $user_id, "id" => $shop_id])->first();
            if ($shopURL != null) {
                $shopURL = $shopURL->toArray();
                $shopCats = UserWebshopsUrls::where(["user_id" => $user_id, "shop_id" => $shop_id])->get();
            }
            if ($shopCats != null) {
                $product_urls = [];
                $products = [];
                foreach ($shopCats as $shopCat) {
                    var_dump($shopCat->url);
                    $crawler = $cc->request('GET', $shopCat->url);
                    $next_link = "";
                    $result = $crawler->filter('.product-item a')->each(function ($node) {
                        return $node->attr("href");
                    });
                    if (sizeof($result) == 0) {
                        $result = $crawler->filter('.products-grid .item h2.product-name a')->each(function ($node) {
                            return $node->attr("href");
                        });
                    } elseif (sizeof($result) == 0) {
                        $result = $crawler->filter('.url.product-image')->each(function ($node) {
                            return $node->attr("href");
                        });

                    }
                    $next_link = $crawler->filter('.paging__link--next')->each(function ($node) {
                        return $node->attr("href");
                    });
                    if (sizeof($next_link) == 0) {
                        $no_pageSize = true;
                        $next_link = $crawler->filter('.pager .pages .next')->each(function ($node) {
                            return $node->attr("href");
                        });
                        if (isset($next_link[1])) {
                            $next_link[0] = $next_link[1];
                        }
                    }
                    if (sizeof($next_link) == 0) {
                        $next_link = $crawler->filter('.pages .next')->each(function ($node) {
                            return $node->attr("href");
                        });
                    }

                    if (sizeof($result) > 0) {
                        $product_urls[] = $result;
                    }

                    while (isset($next_link[0]) && $next_link[0] != "") {
                        $nl = "";
                        if (strstr($next_link[0], $shopCat->url)) {
                            if (strstr($next_link[0], "limit=48") === false) {
                                $nl = $next_link[0] . "&limit=48";
                            } else {
                                $nl = $next_link[0] . "&limit=48";
                            }
                        } else {
                            $nl = $shopCat->url . $next_link[0];
                        }
                        if ($no_pageSize) {
                            $nl = $next_link[0];
                        }

                        $crawler = $cc->request('GET', $nl);
                        $result = $crawler->filter('.product-item a')->each(function ($node) {
                            return $node->attr("href");
                        });
                        if (sizeof($result) == 0) {
                            $result = $crawler->filter('.products-grid .item h2.product-name a')->each(function ($node) {
                                return $node->attr("href");
                            });
                        } elseif (sizeof($result) == 0) {
                            $result = $crawler->filter('.url.product-image')->each(function ($node) {
                                return $node->attr("href");
                            });
                        }
                        $next_link = $crawler->filter('.paging__link--next')->each(function ($node) {
                            return $node->attr("href");
                        });
                        if (sizeof($next_link) == 0) {
                            $no_pageSize = true;
                            $next_link = $crawler->filter('.pager .pages .next')->each(function ($node) {
                                return $node->attr("href");
                            });
                            if (isset($next_link[1])) {
                                $next_link[0] = $next_link[1];
                            }
                        }
                        if (sizeof($next_link) == 0) {
                            $next_link = $crawler->filter('.pages .next')->each(function ($node) {
                                return $node->attr("href");
                            });
                        }

                        if (sizeof($result) > 0) {
                            $product_urls[] = $result;
                        }
                    }
                    foreach ($product_urls as $page) {
                        foreach ($page as $product_link) {
                            $product = [];
                            $brand = $category = $sku = '';
                            $plink = "";
                            $ean = [];
                            if (strstr($product_link, $shopURL["url"])) {
                                $plink = $product_link;
                            } else {
                                $plink = $shopURL["url"] . $product_link;
                            }
                            $crawler = $cc->request('GET', $plink);
                            $name = $crawler->filter('.product-page__heading-row--name')->each(function ($node) {
                                return $node->text();
                            });
                            if (sizeof($name) == 0) {
                                $name = $crawler->filter('.product-shop .product-name .item.name')->each(function ($node) {
                                    return $node->text();
                                });
                            }
                            if (sizeof($name) == 0) {
                                $name = $crawler->filter('.product-name span')->each(function ($node) {
                                    return $node->text();
                                });
                            }

                            if (sizeof($name) == 0) {
                                $name = $crawler->filter('.product-primary-column .product-name h1')->each(function ($node) {
                                    return $node->text();
                                });
                            }

                            $price = $crawler->filter('.product-page__price span')->each(function ($node) {
                                return $node->text();
                            });
                            if (sizeof($price) == 0) {
                                $price = $crawler->filter('.price-box .special-price .price')->each(function ($node) {
                                    return $node->text();
                                });
                                if (sizeof($price) == 0) {
                                    $price = $crawler->filter('.price-box .price')->each(function ($node) {
                                        return $node->text();
                                    });
                                }

                            }
                            $description = $crawler->filter('.accordion--product-details .accordion__block-content p')->each(function ($node) {
                                return $node->text();
                            });

                            if (sizeof($description) == 0) {
                                $description = $crawler->filter('.product-primary-column .description .std')->each(function ($node) {
                                    return $node->text();
                                });
                            }

                            if (sizeof($description) == 0) {
                                $description = $crawler->filter('.short-description .std')->each(function ($node) {
                                    return $node->text();
                                });
                            }

                            if (sizeof($description) == 0) {
                                $description = $crawler->filter('.description .std')->each(function ($node) {
                                    return $node->text();
                                });
                            }

                            $sku = $crawler->filter('.accordion--product-details .accordion__block-content .product-page__detail-value')->each(function ($node) {
                                return $node->text();
                            });
                            if (sizeof($sku) >= 4) {
                                $brand = $sku[2];
                                $category = $sku[3];
                                if (isset($sku[4]))
                                    $sku = $sku[4];
                            }

                            if (sizeof($sku) == 0) {
                                $sku = $crawler->filter('.sku-gender .sku')->each(function ($node) {
                                    return $node->text();
                                });
                                if (isset($sku[0])) {
                                    $sku = preg_replace('/Kode:/', '', $sku[0]);
                                }
                            }
                            if (sizeof($sku) == 0) {
                                $sku = $crawler->filter('.product-shop .product-name .sku')->each(function ($node) {
                                    return $node->text();
                                });

                                if (isset($sku[0])) {
                                    $sku = trim(preg_replace('/Varenummer: /', '', $sku[0]));
                                }
                            }

                            if (sizeof($sku) == 0) {
                                if (is_array($name) && isset($name[0])) {
                                    $sku = explode(" ", $name[0])[0];
                                }
                            }

                            if ($brand == "") {
                                $attributes = $crawler->filter('.product-essential .product-atributes li span.value')->each(function ($node) {
                                    return $node->text();
                                });
                                if (isset($attributes[1])) {
                                    $brand = trim($attributes[1]);
                                }
                                if (isset($attributes[2])) {
                                    $category = trim($attributes[2]);
                                }
                            }

                            if ($brand == "") {
                                $attributes = $crawler->filter('#product-tabs .data-table .data.last')->each(function ($node) {
                                    return $node->text();
                                });
                                if (isset($attributes[0])) {
                                    $ean[0] = trim($attributes[0]);
                                }
                                if (isset($attributes[1])) {
                                    $brand = trim($attributes[1]);
                                }
                                if (isset($attributes[3])) {
                                    $category = trim($attributes[3]);
                                }
                            }

                            if ($brand == "" && isset($name[0])) {
                                $brand = explode(" ", $name[0]);
                                if (isset($brand[0])) {
                                    $brand = $brand[0];
                                }
                            }

                            $image = $crawler->filter('.product-page__image-large')->each(function ($node) {
                                return $node->attr("src");
                            });

                            if (sizeof($image) == 0) {
                                $image = $crawler->filter('.product-image-gallery .gallery-item img')->each(function ($node) {
                                    return $node->attr("src");
                                });
                            }
                            if (sizeof($image) == 0) {
                                $image = $crawler->filter('.product-img-box .thumbs a img')->each(function ($node) {
                                    return $node->attr("src");
                                });
                            }

                            if (sizeof($image) == 0) {
                                $image = $crawler->filter('.product-img-column #zoom-btn')->each(function ($node) {
                                    return $node->attr("href");
                                });
                            }

                            if (!isset($ean) || sizeof($ean) == 0) {
                                $attrs = $crawler->filter('#product-attribute-specs-table tbody .data')->each(function ($node) {
                                    return $node->text();
                                });

                                if (isset($attrs[1])) {
                                    $ean[0] = $attrs[1];
                                }
                                if (isset($attrs[0])) {
                                    $sku = $attrs[0];
                                }
                            }

                            $product = [
                                "name" => isset($name[0]) ? trim(preg_replace('/[^A-Za-z0-9]/', ' ', $name[0])) : "",
                                "price" => isset($price[0]) ? trim(preg_replace('/[^0-9 ,]/', '', $price[0])) : "",
                                "sku" => !is_array($sku) ? trim($sku) : "",
                                "brand" => $brand,
                                "category" => $category,
                                "ean" => isset($ean[0]) ? trim($ean[0]) : "",
                                "isbn" => "",
                                "asin" => "",
                                "min_rsp" => 0,
                                "max_rsp" => 0,
                                "manual_rsp" => false,
                                "sync" => true,
                                "old_price" => "",
                                "image" => isset($image[0]) ? trim($image[0]) : "",
                                "description" => isset($description[0]) ? trim(html_entity_decode($description[0])) : "",
                                "product_link" => $plink,
                            ];

                            if (isset($product) && sizeof($product) > 0) {
                                $crowlModel = new UserCrawlerResult();
                                $old = $crowlModel->where([
                                    "user_id" => $user_id,
                                    "shop_id" => $shop_id,
                                    "name" => trim($product["name"]),
                                    "sku" => trim($product["sku"]),
                                    "ean" => trim($product["ean"]),
                                ])->first();
                                if (isset($old) && (float)$product["price"] != (float)$old->price) {
                                    if (unserialize($old->old_price)) {
                                        $old_price = unserialize($old->old_price);
                                        $old_price[time()] = $old->price;
                                        $product["old_price"] = serialize($old_price);
                                    } else {
                                        $product["old_price"] = serialize([time() => $old->price]);
                                    }
                                    $updated["products"][] = $old;
                                }

                                $output["products"][] = $crowlModel->updateOrCreate(
                                    [
                                        "user_id" => $user_id,
                                        "shop_id" => $shop_id,
                                        "name" => trim($product["name"]),
                                        "sku" => trim($product["sku"]),
                                        "ean" => trim($product["ean"]),
                                    ], $product);
//                                var_dump($output);
                                var_dump("Pre for " . $shop_id . ": " . sizeof($output["products"]));
                            }
                        }

                    }
                }
                $output["uid"] = $user_id;
                $output["shop_id"] = $shop_id;
                $this->sendNotify($output, $updated);
            }
        }
    }


    protected function sendNotify($products = [], $updated = [], $update = false, $shopName = [])
    {
        if (isset($products["products"]) && $products["uid"]) {
            $user = User::find($products["uid"])->toArray();
            $user_meta = UserMeta::where(["user_id" => $products["uid"], "meta_name" => "email"])->first();
            if ($user_meta != null) {
                $user["email"] = $user_meta->meta_value;
            }
            $server_url = Config::get('app.url');
            var_dump($user["email"]);
            $time = date("d-m-y H:i:s", time());
            if ($update){
                Mail::send('notification-email', ["data" => sizeof($products["products"]), "url" => $server_url, "shopName"=> $shopName, "update" => $update, "update_size" => sizeof($updated["products"]), "time" => $time], function ($message) use ($user) {
                    $message->to($user["email"]);
                    $message->subject('Priseshape Notifier');
                });
                var_dump("Email with UP send");
            }else{
                Mail::send('notification-email', ["data" => sizeof($products["products"]), "shopName"=> $shopName, "url" => $server_url, "update" => $update, "update_size" => sizeof($updated["products"]), "time" => $time], function ($message) use ($user) {
                    $message->to($user["email"]);
                    $message->subject('Priseshape Notifier');
                });
                var_dump("Email with START send");
            }
//            dd('Mail Send Successfully');
        }
    }

    protected function clearLink($shopUrl, $url, $host = false)
    {
        $nl = "";
        foreach (explode("/", $shopUrl) as $key => $val) {
            if (sizeof(explode("/", $shopUrl)) - 1 > $key) {
                $old = explode("/", $url);
                foreach ($old as $kk => $vv) {
                    if ($vv == $val) {
                        $val = "";
                    }
                }
                $nl .= $val . "/";
            }
        }
        if ($host) {
            if (sizeof(explode("/", $nl)) > 2) {
                $old = explode("/", $nl);
                $nl = "";
                foreach ($old as $kk => $vv) {
                    if ($kk <= 2) {
                        $nl .= $vv . "/";
                    }
                }
            }
            $nl .= "/";
        }

        if ($nl != "") {
            $nl = str_replace("@", "://", str_replace("//", "", str_replace("://", "@", $nl)));
            $shopUrl = $nl . $url;
        }
        return $shopUrl;
    }

    protected function clean($string)
    {
        $string = str_replace("'", "", $string);
        $string = preg_replace('/[^A-Za-z0-9 -\/]/', '', $string); // Removes special chars.

        return $string;
    }

    public function getProducts(Request $request)
    {
        $data = $request->all();
        $offset = 0;
        $limit = 10;
        if (isset($data["data"]) && isset($data["data"]["cid"])) {
            if (isset($data["data"]["index"])) {
                $offset = $data["data"]["index"];
            }
            if (isset($data["data"]["search"])) {
                $products = UserCrawlerResult::where(function ($query) use ($data){
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

            return response()->json(["status" => "ok", "data" => ["products" => $products, "counter" => $counter]]);
        } else {
            return response()->json(["status" => "error", "data" => ["message" => "Server error. Try again later."]]);
        }
    }

    public function getMatchedProducts(Request $request)
    {
        $data = $request->all();
        $offset = 0;
        $limit = 10;
        if (isset($data["data"]) && isset($data["data"]["pid"])) {
            if (isset($data["data"]["index"])) {
                $offset = $data["data"]["index"];
            }
            $products = UserCrawlerResult::getRawFilteredProductsById($this->guard()->user()->id, $data["data"]["pid"], [], $offset, $limit);
            $counter = UserCrawlerResult::getRawFilteredProductsCountById($this->guard()->user()->id, $data["data"]["pid"], []);

            return response()->json(["status" => "ok", "data" => ["products" => $products, "counter" => $counter]]);
        }
        return response()->json(["status" => "error", "data" => ["products" => [], "counter" => 0]]);
    }

    public function setProduct(Request $request)
    {
        $data = $request->all();
        $uid = $this->guard()->user()->id;
        if (isset($data["data"]) && isset($data["data"]["form"]) && isset($data["data"]["product"])) {
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
            return response()->json(["status" => "ok"]);
        }
    }

    public function deleteUserProduct(Request $request)
    {
        $data = $request->all();
        if (isset($data["pid"]) && isset($data["ppid"])) {
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

            $model = UserMeta::where("user_id", $this->guard()->user()->id)->where("meta_name", "favorite_products");
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
                        "user_id" => $this->guard()->user()->id,
                        "meta_name" => "favorite_products",
                    ],
                    [
                        "meta_value" => serialize(array_unique($olData)),
                        "updated_at" => time()
                    ]);
            }
            $this->setProductRelations($this->guard()->user()->id, $data["pid"]);
            return response()->json(["status" => "ok", "data" => ["message" => "Crawler product deleted."]]);
        } else {
            return response()->json(["status" => "error", "data" => ["message" => "Server error. Try again later."]]);
        }
    }

    public function importProductCrawler(Request $request)
    {
        $data = $request->all();
        $uid = $this->guard()->user()->id;
        $shop_id = 0;
        $product = [];
        $brand = $category = $sku = '';
        $plink = "";
        if (isset($data["dfPid"])) {
            $op = UserProducts::find((int)$data["dfPid"]);
        }

        if (isset($data["url"])) {
            $plink = $data["url"];
            $cc = new Goutte\Client();
            $shopURL = UserWebshops::where(["user_id" => $uid])->get()->all();
            $crawler = $cc->request('GET', $data["url"]);
            $tl = explode("/", $data["url"]);
            if (!isset($data["sid"])) {
                foreach ($shopURL as $key => $val) {
                    $tll = explode("/", $val->url);
                    $link = strstr($this->clearLink($val->url, $data["url"], true), "http://");
                    if (isset($tll[2]) && isset($tl[2])) {
                        if ($tll[2] == $tl[2]) {
                            $shop_id = $val->id;
                        }
                    }
                }
            } else {
                $shop_id = $data["sid"];
            }
            $name = $crawler->filter('.product-page__heading-row--name')->each(function ($node) {
                return $node->text();
            });
            if (sizeof($name) == 0) {
                $name = $crawler->filter('.product-shop .product-name .item.name')->each(function ($node) {
                    return $node->text();
                });
            }
            if (sizeof($name) == 0) {
                $name = $crawler->filter('.product-name span')->each(function ($node) {
                    return $node->text();
                });
            }

            if (sizeof($name) == 0) {
                $name = $crawler->filter('.product-primary-column .product-name h1')->each(function ($node) {
                    return $node->text();
                });
            }

            $price = $crawler->filter('.product-page__price span')->each(function ($node) {
                return $node->text();
            });
            if (sizeof($price) == 0) {
                $price = $crawler->filter('.price-box .special-price .price')->each(function ($node) {
                    return $node->text();
                });
                if (sizeof($price) == 0) {
                    $price = $crawler->filter('.price-box .price')->each(function ($node) {
                        return $node->text();
                    });
                }

            }
            $description = $crawler->filter('.accordion--product-details .accordion__block-content p')->each(function ($node) {
                return $node->text();
            });

            if (sizeof($description) == 0) {
                $description = $crawler->filter('.product-primary-column .description .std')->each(function ($node) {
                    return $node->text();
                });
            }

            if (sizeof($description) == 0) {
                $description = $crawler->filter('.short-description .std')->each(function ($node) {
                    return $node->text();
                });
            }

            if (sizeof($description) == 0) {
                $description = $crawler->filter('.description .std')->each(function ($node) {
                    return $node->text();
                });
            }

            $sku = $crawler->filter('.accordion--product-details .accordion__block-content .product-page__detail-value')->each(function ($node) {
                return $node->text();
            });
            if (sizeof($sku) >= 4) {
                $brand = $sku[2];
                $category = $sku[3];
                if (isset($sku[4]))
                    $sku = $sku[4];
            }

            if (sizeof($sku) == 0) {
                $sku = $crawler->filter('.sku-gender .sku')->each(function ($node) {
                    return $node->text();
                });
                if (isset($sku[0])) {
                    $sku = preg_replace('/Kode:/', '', $sku[0]);
                }
            }
            if (sizeof($sku) == 0) {
                $sku = $crawler->filter('.product-shop .product-name .sku')->each(function ($node) {
                    return $node->text();
                });

                if (isset($sku[0])) {
                    $sku = trim(preg_replace('/Varenummer: /', '', $sku[0]));
                }
            }

            if (sizeof($sku) == 0) {
                if (is_array($name)) {
                    $sku = explode(" ", $name[0])[0];
                }
            }

            if ($brand == "") {
                $attributes = $crawler->filter('.product-essential .product-atributes li span.value')->each(function ($node) {
                    return $node->text();
                });
                if (isset($attributes[1])) {
                    $brand = trim($attributes[1]);
                }
                if (isset($attributes[2])) {
                    $category = trim($attributes[2]);
                }
            }

            if ($brand == "") {
                $attributes = $crawler->filter('#product-tabs .data-table .data.last')->each(function ($node) {
                    return $node->text();
                });
                if (isset($attributes[0])) {
                    $ean[0] = trim($attributes[0]);
                }
                if (isset($attributes[1])) {
                    $brand = trim($attributes[1]);
                }
                if (isset($attributes[3])) {
                    $category = trim($attributes[3]);
                }
            }

            if ($brand == "" && isset($name[0])) {
                $brand = explode(" ", $name[0]);
                if (isset($brand[0])) {
                    $brand = $brand[0];
                }
            }

            $image = $crawler->filter('.product-page__image-large')->each(function ($node) {
                return $node->attr("src");
            });

            if (sizeof($image) == 0) {
                $image = $crawler->filter('.product-image-gallery .gallery-item img')->each(function ($node) {
                    return $node->attr("src");
                });
            }
            if (sizeof($image) == 0) {
                $image = $crawler->filter('.product-img-box .thumbs a img')->each(function ($node) {
                    return $node->attr("src");
                });
            }

            if (sizeof($image) == 0) {
                $image = $crawler->filter('.product-img-column #zoom-btn')->each(function ($node) {
                    return $node->attr("href");
                });
            }

            if (!isset($ean) || sizeof($ean) == 0) {
                $attrs = $crawler->filter('#product-attribute-specs-table tbody .data')->each(function ($node) {
                    return $node->text();
                });

                if (isset($attrs[1])) {
                    $ean[0] = $attrs[1];
                }
                if (isset($attrs[0])) {
                    $sku = $attrs[0];
                }
            }

            if ($op != null) {
                $ean[0] = $op->gid;
                $sku = $op->sku;
            }

            $product = [
                "name" => isset($name[0]) ? trim(preg_replace('/[^A-Za-z0-9]/', ' ', $name[0])) : "",
                "price" => isset($price[0]) ? trim(preg_replace('/[^0-9 ,]/', '', $price[0])) : "",
                "sku" => !is_array($sku) ? trim($sku) : "",
                "brand" => $brand,
                "shop_id" => $shop_id,
                "category" => $category,
                "ean" => isset($ean[0]) ? trim($ean[0]) : "",
                "isbn" => "",
                "asin" => "",
                "min_rsp" => 0,
                "max_rsp" => 0,
                "manual_rsp" => false,
                "sync" => true,
                "old_price" => "",
                "image" => isset($image[0]) ? trim($image[0]) : "",
                "description" => isset($description[0]) ? trim(html_entity_decode($description[0])) : "",
                "product_link" => $plink,
            ];

            if (isset($product) && sizeof($product) > 0) {
                $crowlModel = new UserCrawlerResult();
                $old = $crowlModel->where([
                    "user_id" => $uid,
                    "shop_id" => $shop_id,
                    "name" => trim($product["name"]),
                    "sku" => trim($product["sku"]),
                    "ean" => trim($product["ean"]),
                ])->first();
                if (isset($old) && (float)$product["price"] != (float)$old->price) {
                    if (unserialize($old->old_price)) {
                        $old_price = unserialize($old->old_price);
                        $old_price[time()] = $old->price;
                        $product["old_price"] = serialize($old_price);
                    } else {
                        $product["old_price"] = serialize([time() => $old->price]);
                    }
                    $updated["products"][] = $old;
                }

                $output["products"][] = $crowlModel->updateOrCreate(
                    [
                        "user_id" => $uid,
                        "shop_id" => $shop_id,
                        "name" => trim($product["name"]),
                        "sku" => trim($product["sku"]),
                        "ean" => trim($product["ean"]),
                    ], $product);
            }
            return response()->json(["status" => "ok", "data" => ["message" => "Product added."]]);
        }
        return response()->json(["status" => "error", "data" => ["message" => "Server error try again later."]]);
    }

    public function importFromCSV($uid = null, $shopId = null, $url = null, $shop_url = null, $config = [], $noRel = false)
    {
        $skuName = $gidName = $mpnName = null;
        $outputFile = "";
        $csvData = [];
        $gzip = false;
        $query = [];
        $parts = parse_url($url);
        if (isset($parts['query'])){
            parse_str($parts['query'], $query);
        }else{
            $query["sitemap_id"] = $url;
            $query["api_token"] = "xboyZQd0CXMsvBJ6BOoRy3iSZH0ZbgkaPUM6Aeui0vhwgWYMV5C1aO9iB9xK";
        }

        if (isset($config["newVal"])){
            if (isset($config["newVal"]["datafeedSku"])) $skuName = $config["newVal"]["datafeedSku"];
            if (isset($config["newVal"]["datafeedGid"])) $gidName = $config["newVal"]["datafeedGid"];
            if (isset($config["newVal"]["datafeedMpn"])) $mpnName = $config["newVal"]["datafeedMpn"];
        }
        if (!is_array($config)){
            $config = json_decode($config);
            if (isset($config->sku)) $skuName = $config->sku;
            if (isset($config->ean)) $gidName = $config->ean;
            if (isset($config->mpn)) $mpnName = $config->mpn;
        }

        if (!$uid){
            $uid = $this->guard()->user()->id;
        }
        if (isset($query["sitemap_id"]) && isset($query["api_token"])){
            $wioc = new Client(['token' => $query["api_token"]]);
            $jobs = $wioc->getScrapingJobs($query["sitemap_id"]);
            $tmpjobs = [];
            foreach ($jobs as $sjob){
                $tmpjobs[] = $sjob;
            }
            if (sizeof($tmpjobs) > 0 ){
                $lastJob = $tmpjobs[sizeof($tmpjobs)-1];
                if ($tmpjobs[sizeof($tmpjobs)-1]["stored_record_count"] == 0 && isset($tmpjobs[sizeof($tmpjobs)-2])){
                    $lastJob = $tmpjobs[sizeof($tmpjobs)-2];
                }
                $webShop = UserWebshops::find($shopId);

//                if ($webShop->last_job_id != $lastJob["id"]){
                $webShop->last_job_id = $lastJob["id"];
                $webShop->save();
//                    $outputFile = "/var/www/html/download/tmp/scrapingjob-data{$lastJob["id"]}.csv";
                $outputFile = "/tmp/scrapingjob-data{$lastJob["id"]}.csv";
                $wioc->downloadScrapingJobCSV($lastJob["id"], $outputFile);
//                }

            }else{
                return true;
            }

        }else{
            return false;
        }
        $csvData = $this->csv_to_array($outputFile);
        if ($outputFile != ""){
            unlink($outputFile);
        }
//        if ($url != ""){
//            $csvResponse = $this->call($url, [], "get");
//            if (isset($csvResponse["header"])){
//                $csvResponse["header"] = explode(":", $csvResponse["header"]);
//                foreach ($csvResponse["header"] as $header){
//                    if (strstr(trim($header), "gzip")){
//                        $gzip = true;
//                    }
//                }
//            }
//            if ($gzip){
//                $csvResponse["body"] = gzdecode($csvResponse["body"]);
//            }
//            $temp = $this->temporaryFile("csv-import-" . time() . ".csv", $csvResponse["body"]);
//            $csvData = $this->csv_to_array($temp);
//        }
        $oldCount = 0;
        if (sizeof($csvData) > 0 && $csvData) {
            $oldCount = UserCrawlerResult::where("shop_id", "=", $shopId)->count();
            $ss = [];
            foreach ($csvData as $product) {
                $img = $pl = $cat = $name ="";
                $sku = $ean = $mpn = "null";
                if (isset($product["product-image"])) {
                    if (strstr(trim($product["product-image"]), "//")) {
                        $img = str_replace("//", "http://", trim($product["product-image"]));
                    } else {
                        if (trim($product["product-image"]) != "null") {
                            $tmps = explode("/",trim($product["product-image"]));
                            $mainS = true;
                            foreach ($tmps as $ikey=>$ival){
                                if (!empty($ival) && $ikey > 1 && $ikey < 3){
                                    if (strstr($shop_url, $ival)){
                                        $mainS = false;
                                    }
                                }
                            }
                            if ($mainS){
                                $img = $shop_url . ltrim($product["product-image"], "/");
                            }else{
                                $img = trim($product["product-image"]);
                            }
                        }
                    }
                }
                if (isset($product["product-image-src"])) {
                    if (strstr(trim($product["product-image-src"]), "//") && !strstr(trim($product["product-image-src"]), "http")) {
                        $img = str_replace("//", "http://", trim($product["product-image-src"]));
                    } else {
                        if (trim($product["product-image-src"]) != "null") {
                            $tmps = explode("/",trim($product["product-image-src"]));
                            $mainS = true;
                            foreach ($tmps as $ikey=>$ival){
                                if (!empty($ival) && $ikey >= 0 && $ikey <= 3){
                                    if (strstr($shop_url, $ival)){
                                        $mainS = false;
                                    }
                                    if (strstr("http:", $ival) || strstr("https:", $ival) ){
                                        $mainS = false;
                                    }
                                }
                            }
                            if ($mainS){
                                $img = $shop_url . ltrim($product["product-image-src"], "/");
                            }else{
                                $img = trim($product["product-image-src"]);
                            }
                        }
                    }
                }
                if (strstr($img, "http:http://")){
                    $img = str_replace("http:http://", "http://", $img);
                }

                if (strstr($img, "https:https://")){
                    $img = str_replace("https:https://", "https://", $img);
                }

                if (strstr($img, "https:http://")){
                    $img = str_replace("https:http://", "https://", $img);
                }

                if (strstr($img, "http:https://")){
                    $img = str_replace("http:https://", "https://", $img);
                }
                if ($img != "" && strstr($shop_url, $img)){
                    $img = $shop_url.ltrim($img, "/");
                }

                if ($img != "" && $img[0] == "/"){
                    $img = $shop_url.ltrim($img, "/");
                }

                if (isset($product["product-id"]) && $product["product-id"] != "") $sku = trim($product["product-id"]);
                if (isset($product["product-sku"]) && $product["product-sku"] != "") $sku = trim($product["product-sku"]);
                if (isset($product["product-number"]) && $product["product-number"] != "") $sku = trim($product["product-number"]);

                if (isset($product["product-url-new-href"])) $pl = trim($product["product-url-new-href"]);
                if (isset($product["product-url-href"])) $pl = trim($product["product-url-href"]);
                if (isset($product["product-link-href"])) $pl = trim($product["product-link-href"]);

                if (!strstr($pl, "http://") && !strstr($pl, "https://")){
                    $pl = $shop_url . ltrim($pl, "/");
                }

                if (isset($product["link-href"])) $pl = trim($product["link-href"]);

                if (isset($product["category-link-level-01"])) $cat = trim($product["category-link-level-01"]);
                if (isset($product["category-level-03"])) $cat = trim($product["category-level-03"]);


                if (isset($product["product-gtin"]) && $product["product-gtin"] != "") $ean = trim($product["product-gtin"]);
                if (isset($product["product-mpn"]) && $product["product-mpn"] != "") $mpn = trim($product["product-mpn"]);

                if (isset($product["product-sku"]) && $product["product-sku"] != "" && isset($product["product-number"]) && $product["product-number"] != ""){
                    $sku = trim($product["product-sku"]);
                    $mpn = trim($product["product-number"]);
                }

                $tmpSku = $sku;
                $tmpMpn = $mpn;
                $tmpEan = $ean;

                if ($skuName != null && $skuName != "sku"){
                    if ($skuName == "gid")
                    {
                        if (isset($product["product-gtin"]) && $product["product-gtin"] != ""){
                            $sku = $product["product-gtin"];
                        }else{
                            $sku = "null";
                        }

                    }
                    if ($skuName == "mpn")
                    {
                        if (isset($product["product-mpn"]) && $product["product-mpn"] != ""){
                            $sku = $product["product-mpn"];
                        }else{
                            $sku = "null";
                        }
                    }
                }
                if ($gidName != null && $gidName != "gid"){
                    if ($gidName == "sku")
                    {
                        if ($tmpSku != "null"){
                            $ean = $tmpSku;
                        }else{
                            $ean = "null";
                        }

                    }
                    if ($gidName == "mpn")
                    {
                        if (isset($product["product-mpn"]) && $product["product-mpn"] != ""){
                            $ean = $product["product-mpn"];
                        }else{
                            $ean = "null";
                        }

                    }
                }

                if ($mpnName != null && $mpnName != "mpn"){
                    if ($mpnName == "sku")
                    {
                        if ($tmpSku != "null"){
                            $mpn = $tmpSku;
                        }else{
                            $mpn = "null";
                        }

                    }
                    if ($mpnName == "gid")
                    {
                        if (isset($product["product-gtin"]) && $product["product-gtin"] != ""){
                            $mpn = $product["product-gtin"];
                        }else{
                            $mpn = "null";
                        }
                    }
                }
                if (isset($product["product-price"])){
                    $product["product-price"] = str_replace(",-","",$product["product-price"]);
                    $product["product-price"] = str_replace("DKK","",$product["product-price"]);
                }
                if (isset($product["product-title"])){
                    $name = $product["product-title"];
                }

                if (isset($product["product-tite"])){
                    $name = $product["product-tite"];
                }

                if (isset($product["product-name"])){
                    $name = $product["product-name"];
                }

                $productArr = [
                    "user_id" => $uid,
                    "shop_id" => $shopId,
                    "name" => $name,
                    "price" => isset($product["product-price"])?trim($product["product-price"]):"",

                    "sku" => $sku,
                    "ean" => $ean,
                    "mpn" => $mpn,

                    "sku_old" => $sku,
                    "ean_old" => $ean,
                    "mpn_old" => $mpn,

                    "brand" => isset($product["product-brand"])?trim($product["product-brand"]):"",
                    "category" => $cat,
                    "isbn" => "",
                    "asin" => "",
                    "min_rsp" => 0,
                    "max_rsp" => 0,
                    "manual_rsp" => false,
                    "sync" => true,
                    "old_price" => "",
                    "image" => $img,
                    "description" => isset($product["product-description"])?trim($product["product-description"]):"",
                    "product_link" => trim($pl),
                    "hash" => md5(trim($name).$tmpSku.$tmpEan.$tmpMpn)
                ];
                $model = new UserCrawlerResult();
                $ss["products"][] = $model->updateOrCreate(
                    [
                        "shop_id" => $shopId,
                        "hash" => md5(trim($name).$tmpSku.$tmpEan.$tmpMpn)
                    ],$productArr);
            }
            if (!$noRel){
                $this->setProductRelations($uid, null, $shopId);
            }else{
                $updated["products"] = [];
                foreach ($ss["products"] as $product){
                    $changed = $product->getChanges();
                    if (isset($changed["price"])){
                        $updated["products"][] = $product;
                    }
                }
                $ss["uid"] = $uid;
                $sshop = UserWebshops::where("id", "=", $shopId)->first();
                $newCount = UserCrawlerResult::where("shop_id", "=", $shopId)->count();
                if ((sizeof($updated["products"]) > 0 && $oldCount>0) || ($newCount!=$oldCount && $oldCount>0)){
                    if ($newCount != $oldCount){
                        for($i = 1;$i<=($newCount-$oldCount);$i++){
                            $updated["products"][] = ['test'];
                        }
                    }
                    $this->sendNotify($ss, $updated, true, $sshop);
                }elseif ($oldCount == 0){
                    $this->sendNotify($ss, $updated, false, $sshop);
                }

            }
            return true;
        }else{
            return true;
        }
    }

    function csv_to_array($filename = '', $delimiter = ',')
    {
        if (!file_exists($filename) || !is_readable($filename)) {
            return FALSE;
        }
        $header = NULL;
        $data = array();
        if (($handle = fopen($filename, 'r')) !== FALSE) {
            while (($row = fgetcsv($handle, 0, $delimiter)) !== FALSE) {
                if ($header == NULL) {
                    $header = array();
                    foreach ($row as $val) {
                        $header_raw[] = $val;
                        $hcounts = array_count_values($header_raw);
                        $header[] = $hcounts[$val] > 1 ? $val . $hcounts[$val] : $val;
                    }

                } else {
                    if (sizeof($header) == sizeof($row)){
                        $data[] = array_combine($header, $row);
                    }
                }
            }
            fclose($handle);
        }
        return $data;
    }

    private function temporaryFile($name, $content)
    {
        $file = DIRECTORY_SEPARATOR .
            trim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) .
            DIRECTORY_SEPARATOR .
            ltrim($name, DIRECTORY_SEPARATOR);

        file_put_contents($file, $content);
        register_shutdown_function(function () use ($file) {
            unlink($file);
        });
        return $file;
    }

    private function call($endpoint, $params = array(), $method)
    {

        $ch = $this->ch;

        curl_setopt($ch, CURLOPT_URL, $endpoint);

        if ($method == 'post') {
            curl_setopt($this->ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, []);

            if ($params != null) {
                $params = json_encode($params);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
            }
        }

        if ($method == 'delete') {
            $ch = curl_init();
            curl_setopt($this->ch, CURLOPT_POST, false);
            curl_setopt($this->ch, CURLOPT_HTTPGET, false);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
            if (isset($params)) {
                $json = json_encode($params);
            } else {
                $json = '';
            }
            curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
        }

        if ($method == 'get') {
            curl_setopt($this->ch, CURLOPT_POST, false);
            curl_setopt($this->ch, CURLOPT_HTTPGET, true);
            curl_setopt($ch, CURLOPT_HEADER, 1);
//            curl_setopt($ch, CURLOPT_USERPWD, "$this->username:$this->password");
//            $this->buildAuthHttpHeader ( $params ['SessionToken'], $params ['UserId'] );
        }

        $response_body = curl_exec($ch);
        $info = curl_getinfo($ch);
        if ($method == 'get') {
            $rr = [];
            $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
            $header = substr($response_body, 0, $header_size);
            $rr["body"] = substr($response_body, $header_size);
            $rr["header"] = $header;
            $response_body = $rr;
        }
        if (floor($info ['http_code'] / 100) >= 4) {
            return @file_get_contents($endpoint);
        }

        return $response_body;
    }


    public function setProductRelations($user_id = null, $crawlerProductId = null, $webshopId = null){
        if ($user_id != null){
            if ($crawlerProductId != null){
                $crawlerProduct = UserCrawlerResult::find($crawlerProductId);
            }else{
                $crawlerProduct = UserCrawlerResult::all();
            }
            if ($webshopId!= null){
                $crawlerProduct = UserCrawlerResult::where("shop_id",$webshopId)->get()->all();
            }
            if ($crawlerProductId == null){
                foreach ($crawlerProduct as $cp){
                    $productModel = UserProducts::where(function ($query) use ($cp) {
                        $query->orWhere('sku', "=", $cp->sku)
                            ->orWhere('gid', "=", $cp->gid)
                            ->orWhere('mpn', "=", $cp->mpn);
                    })->where("user_id", $user_id)->get()->all();
//                    $clear = UserProductRelations::where("user_crowler_product_id", $cp->id)->forceDelete();
                    foreach ($productModel as $product){
                        $relationModel = new UserProductRelations();
                        $relationModel->updateOrCreate(
                            [
                                "user_crowler_product_id" => $cp->id,
                                "shop_id" =>$cp->shop_id,
                                "user_product_id" => $product->id
                            ],
                            [
                                "user_crowler_product_id" => $cp->id,
                                "shop_id" =>$cp->shop_id,
                                "user_product_id" => $product->id
                            ]);
//                        $relationModel->user_crowler_product_id = $cp->id;
//                        $relationModel->shop_id = $cp->shop_id;
//                        $relationModel->user_product_id = $product->id;
//                        $relationModel->save();
                    }
                }
            }else{
                $productModel = UserProducts::where(function ($query) use ($crawlerProduct) {
                    $query->orWhere('sku', "=", $crawlerProduct->sku)
                        ->orWhere('gid', "=", $crawlerProduct->gid)
                        ->orWhere('mpn', "=", $crawlerProduct->mpn);
                })->where("user_id", $user_id)->get();
//                $clear = UserProductRelations::where("user_crowler_product_id", $crawlerProduct->id)->forceDelete();
                foreach ($productModel as $product){
                    $relationModel = new UserProductRelations();
                    $relationModel->updateOrCreate(
                        [
                            "user_crowler_product_id" => $crawlerProduct->id,
                            "shop_id" =>$crawlerProduct->shop_id,
                            "user_product_id" => $product->id
                        ],
                        [
                            "user_crowler_product_id" => $crawlerProduct->id,
                            "shop_id" =>$crawlerProduct->shop_id,
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

    public function guard()
    {
        return Auth::guard();
    }

    public function crawlerNotifyHandler(Request $request){
        if (isset($_GET['user_id']) && isset($_POST['sitemap_id'])){
            $uid = \Illuminate\Support\Facades\Crypt::decrypt($_GET['user_id']);
            $sitemapId = (int) $_POST['sitemap_id'];
            if (isset($_POST['status']) && $_POST['status'] == "finished"){
                $urls = UserWebshopsUrls::where("user_id",$uid)->get();
                if (sizeof($urls)>0){
                    foreach ($urls as $url){
                        $query = [];
                        $parts = parse_url($url->url);
                        if (isset($parts['query'])){
                            parse_str($parts['query'], $query);
                        }else{
                            $query["sitemap_id"] = $url;
                            $query["api_token"] = "xboyZQd0CXMsvBJ6BOoRy3iSZH0ZbgkaPUM6Aeui0vhwgWYMV5C1aO9iB9xK";
                        }
                        if (isset($query["sitemap_id"])){
                            $shop = UserWebshops::find($url->shop_id);
                            $this->importFromCSV($uid, $url->shop_id, $url->url, $shop->url, $shop->config, true);
                        }
                    }
                }
            }
        }
    }
}
