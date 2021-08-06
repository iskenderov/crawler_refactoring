<?php

namespace App\Services;

use App\Models\UserCrawlerResult;
use App\Models\UserWebshops;
use App\Models\UserWebshopsUrls;
use Illuminate\Http\Request;

class CrawlerService
{

    /**
     * @var Request
     */
    private $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public function run($user_id = null, $shop_id = null, $shopURLNext = null, $next = false)
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
                NotifyService::send($output, $updated);
            }
        }
    }

    public function crawlerNotifyHandler(ImportService $importService)
    {
        if (isset($_GET['user_id']) && isset($_POST['sitemap_id'])) {
            $uid = \Illuminate\Support\Facades\Crypt::decrypt($_GET['user_id']);
            $sitemapId = (int)$_POST['sitemap_id'];
            if (isset($_POST['status']) && $_POST['status'] == "finished") {
                $urls = UserWebshopsUrls::where("user_id", $uid)->get();
                if (sizeof($urls) > 0) {
                    foreach ($urls as $url) {
                        $query = [];
                        $parts = parse_url($url->url);
                        if (isset($parts['query'])) {
                            parse_str($parts['query'], $query);
                        } else {
                            $query["sitemap_id"] = $url;
                            $query["api_token"] = "xboyZQd0CXMsvBJ6BOoRy3iSZH0ZbgkaPUM6Aeui0vhwgWYMV5C1aO9iB9xK";
                        }
                        if (isset($query["sitemap_id"])) {
                            $shop = UserWebshops::find($url->shop_id);
                            $importService->importFromCSV($uid, $url->shop_id, $url->url, $shop->url, $shop->config, true);
                        }
                    }
                }
            }
        }
    }
}
