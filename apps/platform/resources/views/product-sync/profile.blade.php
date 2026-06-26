@extends('layouts.app')

@section('content')
    <section class="panel">
        <h1>Product Sync Settings</h1>
        <p>These settings control how products are prepared before any future sync to Front.</p>
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
                    <option value="production" @selected($profile->mode === 'production') @disabled(!$productionWritesEnabled)>Production</option>
                </select>
                @unless ($productionWritesEnabled)
                    <p class="muted">Production mode is unavailable because production writes are disabled.</p>
                @endunless

                <label for="max_products_per_run">Max products per run</label>
                <input id="max_products_per_run" name="max_products_per_run" type="number" min="1" max="1000" value="{{ old('max_products_per_run', $profile->max_products_per_run) }}">

                <label><input type="checkbox" name="sync_only_opted_in_products" value="1" @checked(old('sync_only_opted_in_products', $profile->sync_only_opted_in_products))> Only sync products opted in for Front</label>
                <label><input type="checkbox" name="include_simple_products" value="1" @checked(old('include_simple_products', $profile->include_simple_products))> Include simple products</label>
                <label><input type="checkbox" name="include_variable_products" value="1" @checked(old('include_variable_products', $profile->include_variable_products))> Include variable products</label>
                <label><input type="checkbox" name="require_sku" value="1" @checked(old('require_sku', $profile->require_sku))> Require SKU</label>
                <label><input type="checkbox" name="require_gtin" value="1" @checked(old('require_gtin', $profile->require_gtin))> Require GTIN/EAN</label>
                <label><input type="checkbox" name="require_price" value="1" @checked(old('require_price', $profile->require_price))> Require price</label>
            </section>

            <section class="panel">
                <h2>Advanced Settings</h2>
                <div class="warning">Technical settings. Only change these if you know what they do.</div>

                <label><input type="checkbox" name="include_variations" value="1" @checked(old('include_variations', $profile->include_variations))> Include variations</label>
                <label><input type="checkbox" name="require_brand" value="1" @checked(old('require_brand', $profile->require_brand))> Require brand</label>
                <label><input type="checkbox" name="require_category" value="1" @checked(old('require_category', $profile->require_category))> Require category</label>

                <label for="max_products_per_batch">Batch size</label>
                <input id="max_products_per_batch" name="max_products_per_batch" type="number" min="1" max="250" value="{{ old('max_products_per_batch', $profile->max_products_per_batch) }}">

                <label for="woo_query_limit">Woo query limit</label>
                <input id="woo_query_limit" name="woo_query_limit" type="number" min="10" max="250" value="{{ old('woo_query_limit', $profile->woo_query_limit) }}">

                <label for="front_write_limit">Front write limit</label>
                <input id="front_write_limit" name="front_write_limit" type="number" min="1" max="100" value="{{ old('front_write_limit', $profile->front_write_limit) }}">

                <label for="default_front_group_strategy">Category/group mapping strategy</label>
                <input id="default_front_group_strategy" name="default_front_group_strategy" value="{{ old('default_front_group_strategy', $profile->default_front_group_strategy) }}">

                <label for="default_front_subgroup_strategy">Subgroup mapping strategy</label>
                <input id="default_front_subgroup_strategy" name="default_front_subgroup_strategy" value="{{ old('default_front_subgroup_strategy', $profile->default_front_subgroup_strategy) }}">

                <label for="default_front_brand_strategy">Brand strategy</label>
                <input id="default_front_brand_strategy" name="default_front_brand_strategy" value="{{ old('default_front_brand_strategy', $profile->default_front_brand_strategy) }}">

                <label for="price_strategy">Price strategy</label>
                <select id="price_strategy" name="price_strategy">
                    <option value="regular_price_only" @selected($profile->price_strategy === 'regular_price_only')>Regular price only</option>
                    <option value="regular_and_sale_preview" @selected($profile->price_strategy === 'regular_and_sale_preview')>Regular and sale price preview</option>
                    <option value="pricelist_v2_later" @selected($profile->price_strategy === 'pricelist_v2_later')>PriceListV2 later</option>
                </select>

                <label for="stock_strategy">Stock strategy</label>
                <select id="stock_strategy" name="stock_strategy">
                    <option value="do_not_sync_stock_yet" @selected($profile->stock_strategy === 'do_not_sync_stock_yet')>Do not sync stock yet</option>
                    <option value="preview_only" @selected($profile->stock_strategy === 'preview_only')>Preview only</option>
                </select>
            </section>

            <button type="submit">Save settings</button>
        </form>
    @else
        <section class="panel">
            <p>Create an organization before configuring product sync.</p>
        </section>
    @endif
@endsection
