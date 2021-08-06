<?php

namespace App\Services;

use App\Helpers\Helpers;
use App\Models\UserCrawlerResult;
use App\Models\UserProducts;
use App\Models\UserWebshops;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use WebScraper\ApiClient\Client;

class ImportService
{
    public function importProductCrawler(array $data): array
    {
        $uid = Auth::id();
        $shop_id = 0;
        $product = [];
        $brand = $category = $sku = '';
        $plink = "";
        if (isset($data["dfPid"])) {
            $op = UserProducts::find((int)$data["dfPid"]);
        }
        $plink = $data["url"];
        $cc = new Goutte\Client();
        $shopURL = UserWebshops::where(["user_id" => $uid])->get()->all();
        $crawler = $cc->request('GET', $data["url"]);
        $tl = explode("/", $data["url"]);
        if (!isset($data["sid"])) {
            foreach ($shopURL as $key => $val) {
                $tll = explode("/", $val->url);
                $link = strstr(Helpers::clearLink($val->url, $data["url"], true), "http://");
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
        return ["status" => "ok", "data" => ["message" => "Product added."]];
    }

    public function importFromCSV($uid = null, $shopId = null, $url = null, $shop_url = null, $config = [], $noRel = false)
    {
        $skuName = $gidName = $mpnName = null;
        $outputFile = "";
        $csvData = [];
        $gzip = false;
        $query = [];
        $parts = parse_url($url);
        if (isset($parts['query'])) {
            parse_str($parts['query'], $query);
        } else {
            $query["sitemap_id"] = $url;
            $query["api_token"] = "xboyZQd0CXMsvBJ6BOoRy3iSZH0ZbgkaPUM6Aeui0vhwgWYMV5C1aO9iB9xK";
        }

        if (isset($config["newVal"])) {
            if (isset($config["newVal"]["datafeedSku"])) $skuName = $config["newVal"]["datafeedSku"];
            if (isset($config["newVal"]["datafeedGid"])) $gidName = $config["newVal"]["datafeedGid"];
            if (isset($config["newVal"]["datafeedMpn"])) $mpnName = $config["newVal"]["datafeedMpn"];
        }
        if (!is_array($config)) {
            $config = json_decode($config);
            if (isset($config->sku)) $skuName = $config->sku;
            if (isset($config->ean)) $gidName = $config->ean;
            if (isset($config->mpn)) $mpnName = $config->mpn;
        }

        if (!$uid) {
            $uid = Auth::id();
        }
        if (isset($query["sitemap_id"]) && isset($query["api_token"])) {
            $wioc = new Client(['token' => $query["api_token"]]);
            $jobs = $wioc->getScrapingJobs($query["sitemap_id"]);
            $tmpjobs = [];
            foreach ($jobs as $sjob) {
                $tmpjobs[] = $sjob;
            }
            if (sizeof($tmpjobs) > 0) {
                $lastJob = $tmpjobs[sizeof($tmpjobs) - 1];
                if ($tmpjobs[sizeof($tmpjobs) - 1]["stored_record_count"] == 0 && isset($tmpjobs[sizeof($tmpjobs) - 2])) {
                    $lastJob = $tmpjobs[sizeof($tmpjobs) - 2];
                }
                $webShop = UserWebshops::find($shopId);

//                if ($webShop->last_job_id != $lastJob["id"]){
                $webShop->last_job_id = $lastJob["id"];
                $webShop->save();
//                    $outputFile = "/var/www/html/download/tmp/scrapingjob-data{$lastJob["id"]}.csv";
                $outputFile = "/tmp/scrapingjob-data{$lastJob["id"]}.csv";
                $wioc->downloadScrapingJobCSV($lastJob["id"], $outputFile);
//                }

            } else {
                return true;
            }

        } else {
            return false;
        }
        $csvData = Helpers::csv_to_array($outputFile);
        if ($outputFile != "") {
            unlink($outputFile);
        }
//        if ($url != ""){
//            $csvResponse = $this->client->call($url, [], "get");
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
                $img = $pl = $cat = $name = "";
                $sku = $ean = $mpn = "null";
                if (isset($product["product-image"])) {
                    if (strstr(trim($product["product-image"]), "//")) {
                        $img = str_replace("//", "http://", trim($product["product-image"]));
                    } else {
                        if (trim($product["product-image"]) != "null") {
                            $tmps = explode("/", trim($product["product-image"]));
                            $mainS = true;
                            foreach ($tmps as $ikey => $ival) {
                                if (!empty($ival) && $ikey > 1 && $ikey < 3) {
                                    if (strstr($shop_url, $ival)) {
                                        $mainS = false;
                                    }
                                }
                            }
                            if ($mainS) {
                                $img = $shop_url . ltrim($product["product-image"], "/");
                            } else {
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
                            $tmps = explode("/", trim($product["product-image-src"]));
                            $mainS = true;
                            foreach ($tmps as $ikey => $ival) {
                                if (!empty($ival) && $ikey >= 0 && $ikey <= 3) {
                                    if (strstr($shop_url, $ival)) {
                                        $mainS = false;
                                    }
                                    if (strstr("http:", $ival) || strstr("https:", $ival)) {
                                        $mainS = false;
                                    }
                                }
                            }
                            if ($mainS) {
                                $img = $shop_url . ltrim($product["product-image-src"], "/");
                            } else {
                                $img = trim($product["product-image-src"]);
                            }
                        }
                    }
                }
                if (strstr($img, "http:http://")) {
                    $img = str_replace("http:http://", "http://", $img);
                }

                if (strstr($img, "https:https://")) {
                    $img = str_replace("https:https://", "https://", $img);
                }

                if (strstr($img, "https:http://")) {
                    $img = str_replace("https:http://", "https://", $img);
                }

                if (strstr($img, "http:https://")) {
                    $img = str_replace("http:https://", "https://", $img);
                }
                if ($img != "" && strstr($shop_url, $img)) {
                    $img = $shop_url . ltrim($img, "/");
                }

                if ($img != "" && $img[0] == "/") {
                    $img = $shop_url . ltrim($img, "/");
                }

                if (isset($product["product-id"]) && $product["product-id"] != "") $sku = trim($product["product-id"]);
                if (isset($product["product-sku"]) && $product["product-sku"] != "") $sku = trim($product["product-sku"]);
                if (isset($product["product-number"]) && $product["product-number"] != "") $sku = trim($product["product-number"]);

                if (isset($product["product-url-new-href"])) $pl = trim($product["product-url-new-href"]);
                if (isset($product["product-url-href"])) $pl = trim($product["product-url-href"]);
                if (isset($product["product-link-href"])) $pl = trim($product["product-link-href"]);

                if (!strstr($pl, "http://") && !strstr($pl, "https://")) {
                    $pl = $shop_url . ltrim($pl, "/");
                }

                if (isset($product["link-href"])) $pl = trim($product["link-href"]);

                if (isset($product["category-link-level-01"])) $cat = trim($product["category-link-level-01"]);
                if (isset($product["category-level-03"])) $cat = trim($product["category-level-03"]);


                if (isset($product["product-gtin"]) && $product["product-gtin"] != "") $ean = trim($product["product-gtin"]);
                if (isset($product["product-mpn"]) && $product["product-mpn"] != "") $mpn = trim($product["product-mpn"]);

                if (isset($product["product-sku"]) && $product["product-sku"] != "" && isset($product["product-number"]) && $product["product-number"] != "") {
                    $sku = trim($product["product-sku"]);
                    $mpn = trim($product["product-number"]);
                }

                $tmpSku = $sku;
                $tmpMpn = $mpn;
                $tmpEan = $ean;

                if ($skuName != null && $skuName != "sku") {
                    if ($skuName == "gid") {
                        if (isset($product["product-gtin"]) && $product["product-gtin"] != "") {
                            $sku = $product["product-gtin"];
                        } else {
                            $sku = "null";
                        }

                    }
                    if ($skuName == "mpn") {
                        if (isset($product["product-mpn"]) && $product["product-mpn"] != "") {
                            $sku = $product["product-mpn"];
                        } else {
                            $sku = "null";
                        }
                    }
                }
                if ($gidName != null && $gidName != "gid") {
                    if ($gidName == "sku") {
                        if ($tmpSku != "null") {
                            $ean = $tmpSku;
                        } else {
                            $ean = "null";
                        }

                    }
                    if ($gidName == "mpn") {
                        if (isset($product["product-mpn"]) && $product["product-mpn"] != "") {
                            $ean = $product["product-mpn"];
                        } else {
                            $ean = "null";
                        }

                    }
                }

                if ($mpnName != null && $mpnName != "mpn") {
                    if ($mpnName == "sku") {
                        if ($tmpSku != "null") {
                            $mpn = $tmpSku;
                        } else {
                            $mpn = "null";
                        }

                    }
                    if ($mpnName == "gid") {
                        if (isset($product["product-gtin"]) && $product["product-gtin"] != "") {
                            $mpn = $product["product-gtin"];
                        } else {
                            $mpn = "null";
                        }
                    }
                }
                if (isset($product["product-price"])) {
                    $product["product-price"] = str_replace(",-", "", $product["product-price"]);
                    $product["product-price"] = str_replace("DKK", "", $product["product-price"]);
                }
                if (isset($product["product-title"])) {
                    $name = $product["product-title"];
                }

                if (isset($product["product-tite"])) {
                    $name = $product["product-tite"];
                }

                if (isset($product["product-name"])) {
                    $name = $product["product-name"];
                }

                $productArr = [
                    "user_id" => $uid,
                    "shop_id" => $shopId,
                    "name" => $name,
                    "price" => isset($product["product-price"]) ? trim($product["product-price"]) : "",

                    "sku" => $sku,
                    "ean" => $ean,
                    "mpn" => $mpn,

                    "sku_old" => $sku,
                    "ean_old" => $ean,
                    "mpn_old" => $mpn,

                    "brand" => isset($product["product-brand"]) ? trim($product["product-brand"]) : "",
                    "category" => $cat,
                    "isbn" => "",
                    "asin" => "",
                    "min_rsp" => 0,
                    "max_rsp" => 0,
                    "manual_rsp" => false,
                    "sync" => true,
                    "old_price" => "",
                    "image" => $img,
                    "description" => isset($product["product-description"]) ? trim($product["product-description"]) : "",
                    "product_link" => trim($pl),
                    "hash" => md5(trim($name) . $tmpSku . $tmpEan . $tmpMpn)
                ];
                $model = new UserCrawlerResult();
                $ss["products"][] = $model->updateOrCreate(
                    [
                        "shop_id" => $shopId,
                        "hash" => md5(trim($name) . $tmpSku . $tmpEan . $tmpMpn)
                    ], $productArr);
            }
            if (!$noRel) {
                $this->setProductRelations($uid, null, $shopId);
            } else {
                $updated["products"] = [];
                foreach ($ss["products"] as $product) {
                    $changed = $product->getChanges();
                    if (isset($changed["price"])) {
                        $updated["products"][] = $product;
                    }
                }
                $ss["uid"] = $uid;
                $sshop = UserWebshops::where("id", "=", $shopId)->first();
                $newCount = UserCrawlerResult::where("shop_id", "=", $shopId)->count();
                if ((sizeof($updated["products"]) > 0 && $oldCount > 0) || ($newCount != $oldCount && $oldCount > 0)) {
                    if ($newCount != $oldCount) {
                        for ($i = 1; $i <= ($newCount - $oldCount); $i++) {
                            $updated["products"][] = ['test'];
                        }
                    }
                    NotifyService::send($ss, $updated, true, $sshop);
                } elseif ($oldCount == 0) {
                    NotifyService::send($ss, $updated, false, $sshop);
                }

            }
            return true;
        } else {
            return true;
        }
    }

}
