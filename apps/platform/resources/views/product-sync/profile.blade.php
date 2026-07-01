@extends('layouts.app')

@section('content')
    <section class="panel page-header">
        <span class="kicker">Technical settings</span>
        <h1>Product Sync Settings</h1>
        <p>These settings control how products are prepared before any future sync to Front. Most store owners should not need to change these during normal use.</p>
        <div class="notice">Preview only is the safest mode. Production mode is disabled unless production writes are explicitly enabled.</div>
    </section>

    @if ($profile)
        <form method="post" action="{{ route('product-sync.profile.update') }}">
            @csrf
            <section class="panel">
                <h2>Simple Settings</h2>

                <label for="mode">Sync mode</label>
                <select id="mode" name="mode">
                    <option value="preview_only" @selected($profile->mode === 'preview_only')>Preview only</option>
                    <option value="limited_write_test" @selected($profile->mode === 'limited_write_test')>Limited write test</option>
                    <option value="staging_batch" @selected($profile->mode === 'staging_batch')>Staging batch</option>
                    <option value="initial_full_sync" @selected($profile->mode === 'initial_full_sync')>Initial full sync planning</option>
                    <option value="incremental_sync" @selected($profile->mode === 'incremental_sync')>Incremental sync planning</option>
                    <option value="production" @selected($profile->mode === 'production') @disabled(!$productionWritesEnabled)>Production</option>
                </select>
                @unless ($productionWritesEnabled)
                    <p class="muted">Production mode is unavailable because production writes are disabled.</p>
                @endunless

                <label for="sync_scope">Sync scope</label>
                <select id="sync_scope" name="sync_scope">
                    <option value="selected_only" @selected($profile->sync_scope === 'selected_only')>Selected products only</option>
                    <option value="all_active_products" @selected($profile->sync_scope === 'all_active_products')>All active products later</option>
                    <option value="changed_since_last_sync" @selected($profile->sync_scope === 'changed_since_last_sync')>Changed since last sync later</option>
                    <option value="failed_only" @selected($profile->sync_scope === 'failed_only')>Failed products only later</option>
                    <option value="category_filter" @selected($profile->sync_scope === 'category_filter')>Category filter later</option>
                    <option value="brand_filter" @selected($profile->sync_scope === 'brand_filter')>Brand filter later</option>
                </select>

                <label for="max_products_per_run">Max products per run</label>
                <input id="max_products_per_run" name="max_products_per_run" type="number" min="1" max="50000" value="{{ old('max_products_per_run', $profile->max_products_per_run) }}">

                <label><input type="checkbox" name="sync_only_opted_in_products" value="1" @checked(old('sync_only_opted_in_products', $profile->sync_only_opted_in_products))> Only sync products opted in for Front</label>
                <label><input type="checkbox" name="include_simple_products" value="1" @checked(old('include_simple_products', $profile->include_simple_products))> Include simple products</label>
                <label><input type="checkbox" name="include_variable_products" value="1" @checked(old('include_variable_products', $profile->include_variable_products))> Include variable products</label>
                <label><input type="checkbox" name="include_variations" value="1" @checked(old('include_variations', $profile->include_variations))> Include variations</label>
                <label><input type="checkbox" name="require_sku" value="1" @checked(old('require_sku', $profile->require_sku))> Require SKU</label>
                <label><input type="checkbox" name="require_gtin" value="1" @checked(old('require_gtin', $profile->require_gtin))> Require GTIN/EAN</label>
                <label><input type="checkbox" name="require_price" value="1" @checked(old('require_price', $profile->require_price))> Require price</label>
            </section>

            <section class="panel">
                <h2>Advanced Settings</h2>
                <div class="warning">Technical settings. Only change these if you know what they do.</div>

                <label><input type="checkbox" name="include_draft_products" value="1" @checked(old('include_draft_products', $profile->include_draft_products))> Include draft products later</label>
                <label><input type="checkbox" name="include_private_products" value="1" @checked(old('include_private_products', $profile->include_private_products))> Include private products later</label>
                <label><input type="checkbox" name="include_out_of_stock_products" value="1" @checked(old('include_out_of_stock_products', $profile->include_out_of_stock_products))> Include out-of-stock products</label>
                <label><input type="checkbox" name="exclude_discontinued_products" value="1" @checked(old('exclude_discontinued_products', $profile->exclude_discontinued_products))> Exclude discontinued products</label>
                <label><input type="checkbox" name="require_brand" value="1" @checked(old('require_brand', $profile->require_brand))> Require brand</label>
                <label><input type="checkbox" name="require_category" value="1" @checked(old('require_category', $profile->require_category))> Require category</label>

                <label for="max_products_per_batch">Batch size</label>
                <input id="max_products_per_batch" name="max_products_per_batch" type="number" min="1" max="250" value="{{ old('max_products_per_batch', $profile->max_products_per_batch) }}">

                <label for="woo_page_size">Woo page size</label>
                <input id="woo_page_size" name="woo_page_size" type="number" min="10" max="100" value="{{ old('woo_page_size', $profile->woo_page_size) }}">

                <label for="front_page_size">Front page size</label>
                <input id="front_page_size" name="front_page_size" type="number" min="10" max="100" value="{{ old('front_page_size', $profile->front_page_size) }}">

                <label for="rate_limit_per_minute">Rate limit per minute</label>
                <input id="rate_limit_per_minute" name="rate_limit_per_minute" type="number" min="1" max="1000" value="{{ old('rate_limit_per_minute', $profile->rate_limit_per_minute) }}">

                <label for="max_runtime_seconds">Max runtime seconds</label>
                <input id="max_runtime_seconds" name="max_runtime_seconds" type="number" min="30" max="3600" value="{{ old('max_runtime_seconds', $profile->max_runtime_seconds) }}">

                <label for="product_identity_strategy">Identity strategy</label>
                <select id="product_identity_strategy" name="product_identity_strategy">
                    <option value="woo_id_as_front_extid" @selected($profile->product_identity_strategy === 'woo_id_as_front_extid')>Woo ID as Front external ID</option>
                    <option value="sku_as_front_extid" @selected($profile->product_identity_strategy === 'sku_as_front_extid')>SKU as Front external ID</option>
                    <option value="gtin_as_primary" @selected($profile->product_identity_strategy === 'gtin_as_primary')>GTIN as primary identifier</option>
                </select>

                <label for="gtin_field_strategy">GTIN/EAN strategy</label>
                <select id="gtin_field_strategy" name="gtin_field_strategy">
                    <option value="auto_detect" @selected($profile->gtin_field_strategy === 'auto_detect')>Auto-detect candidate fields</option>
                    <option value="configured_meta_key" @selected($profile->gtin_field_strategy === 'configured_meta_key')>Use configured meta key</option>
                    <option value="zettle_barcode_fields" @selected($profile->gtin_field_strategy === 'zettle_barcode_fields')>Use Zettle barcode fields</option>
                </select>

                <label for="configured_gtin_meta_key">Configured GTIN/EAN meta key</label>
                <input id="configured_gtin_meta_key" name="configured_gtin_meta_key" value="{{ old('configured_gtin_meta_key', $profile->configured_gtin_meta_key) }}">

                <label for="category_mapping_strategy">Category/group mapping strategy</label>
                <input id="category_mapping_strategy" name="category_mapping_strategy" value="{{ old('category_mapping_strategy', $profile->category_mapping_strategy) }}">

                <label for="brand_mapping_strategy">Brand strategy</label>
                <input id="brand_mapping_strategy" name="brand_mapping_strategy" value="{{ old('brand_mapping_strategy', $profile->brand_mapping_strategy) }}">

                <label for="price_strategy">Price strategy</label>
                <select id="price_strategy" name="price_strategy">
                    <option value="regular_price_only" @selected($profile->price_strategy === 'regular_price_only')>Regular price only</option>
                    <option value="regular_price_now_sale_price_later" @selected($profile->price_strategy === 'regular_price_now_sale_price_later')>Regular price now, sale price later</option>
                    <option value="pricelist_v2_later" @selected($profile->price_strategy === 'pricelist_v2_later')>PriceListV2 later</option>
                </select>
                <label for="sale_price_list_name">Front sale price list name</label>
                <input id="sale_price_list_name" name="sale_price_list_name" value="{{ old('sale_price_list_name', $profile->sale_price_list_name ?? 'WooCommerce Sale Prices') }}">
                <p class="muted">Used by staging sale price sync through Front <code>POST /api/PricelistV2</code>.</p>

                <label for="stock_strategy">Stock strategy</label>
                <select id="stock_strategy" name="stock_strategy">
                    <option value="do_not_sync_stock_yet" @selected($profile->stock_strategy === 'do_not_sync_stock_yet')>Do not sync stock yet</option>
                    <option value="preview_only" @selected($profile->stock_strategy === 'preview_only')>Preview only</option>
                    <option value="stock_sync_later" @selected($profile->stock_strategy === 'stock_sync_later')>Stock sync later</option>
                </select>

                <label><input type="checkbox" name="incremental_sync_enabled" value="1" @checked(old('incremental_sync_enabled', $profile->incremental_sync_enabled))> Incremental sync enabled later</label>
                <label><input type="checkbox" name="webhook_updates_enabled" value="1" @checked(old('webhook_updates_enabled', $profile->webhook_updates_enabled))> WooCommerce webhook updates later</label>
                <label><input type="checkbox" name="reconciliation_enabled" value="1" @checked(old('reconciliation_enabled', $profile->reconciliation_enabled))> Nightly reconciliation later</label>
            </section>

            <button type="submit">Save settings</button>
        </form>
    @else
        <section class="panel">
            <p>Create an organization before configuring product sync.</p>
        </section>
    @endif
@endsection
