<?php

namespace App\Http\Controllers;

use App\Http\Requests\CrawlerNotifyHandlerRequest;
use App\Http\Requests\DeleteUserProductRequest;
use App\Http\Requests\GetMatchedProductsRequest;
use App\Http\Requests\GetProductsRequest;
use App\Http\Requests\ImportProductCrawlerRequest;
use App\Http\Requests\SetProductRequest;
use App\Services\CrawlerService;
use App\Services\ImportService;
use App\Services\ProductsService;
use Illuminate\Http\Request;


class CrawlerController2 extends Controller
{
    /**
     * @var CrawlerService
     */
    private $crawlerService;
    /**
     * @var ProductsService
     */
    private $productService;
    /**
     * @var ImportService
     */
    private $importService;

    public function __construct(Request $request, ProductsService $productService, ImportService $importService)
    {
        $this->crawlerService = new CrawlerService($request);
        $this->productService = $productService;
        $this->importService = $importService;
    }

    public function crawler($user_id = null, $shop_id = null, $shopURLNext = null, $next = false)
    {
        $this->crawlerService->run($user_id = null, $shop_id = null, $shopURLNext = null, $next = false);
    }

    public function getProducts(GetProductsRequest $request)
    {
        $validated = $request->validated();

        return \response()->json($this->productService->getProducts($validated));
    }

    public function getMatchedProducts(GetMatchedProductsRequest $request)
    {
        $validated = $request->validated();
        return \response()->json($this->productService->getMatchedProducts($validated));
    }

    public function setProduct(SetProductRequest $request)
    {
        $validated = $request->validated();
        return \response()->json($this->productService->setProduct($validated));
    }

    public function deleteUserProduct(DeleteUserProductRequest $request)
    {
        $validated = $request->validated();
        return \response()->json($this->productService->deleteUserProduct($validated));
    }

    public function importProductCrawler(ImportProductCrawlerRequest $request)
    {
        $validated = $request->validated();
        return \response()->json($this->importService->importProductCrawler($validated));
    }

    public function crawlerNotifyHandler(CrawlerNotifyHandlerRequest $request)
    {
        $this->crawlerService->crawlerNotifyHandler($this->importService);
    }

}
