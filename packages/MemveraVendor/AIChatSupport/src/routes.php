<?php

use Illuminate\Support\Facades\Route;
use MemveraVendor\AIChatSupport\Controllers\GuideController;
use MemveraVendor\AIChatSupport\Controllers\SupportController;

Route::post('guide/upload', [GuideController::class, 'uploadGuide'])->name('uploadGuide');
Route::post('support/query', [SupportController::class, 'handleQuery'])->name('handleQuery');
