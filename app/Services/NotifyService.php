<?php

namespace App\Services;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;

class NotifyService
{
    public static function send($products = [], $updated = [], $update = false, $shopName = [])
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
}
