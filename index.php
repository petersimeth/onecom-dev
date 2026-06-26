<?php
declare(strict_types=1);

require_once __DIR__ . '/src/bootstrap.php';

$shopSignalData = loadShopSignalData();
$databaseConnected = $shopSignalData['source'] === 'database';
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta
      name="description"
      content="ShopSignal — a modern Shopify store intelligence dashboard mockup."
    />
    <title>ShopSignal — Shopify intelligence</title>
    <link rel="stylesheet" href="<?= htmlspecialchars(shopSignalVersionedAssetUrl('styles.css')) ?>" />
  </head>
  <body>
    <div class="app-shell">
      <aside class="sidebar" id="sidebar">
        <div class="brand">
          <span class="brand-mark" aria-hidden="true">
            <svg viewBox="0 0 32 32">
              <path d="M8.2 9.4 16 4l7.8 5.4v12.9L16 28l-7.8-5.7V9.4Z" />
              <path d="m11.8 17.1 2.7 2.7 5.9-7" />
            </svg>
          </span>
          <span>ShopSignal</span>
        </div>

        <nav class="main-nav" aria-label="Main navigation">
          <p class="nav-label">Workspace</p>
          <button class="nav-item active" data-view="explorer">
            <span data-icon="search"></span>
            Store explorer
            <kbd>⌘ K</kbd>
          </button>
          <button class="nav-item" data-view="lists">
            <span data-icon="bookmark"></span>
            Saved lists
            <span class="nav-count" id="savedListNavCount">0</span>
          </button>
          <button class="nav-item" data-view="signals">
            <span data-icon="activity"></span>
            Signals
            <span class="status-dot"></span>
          </button>
          <button class="nav-item" data-view="market">
            <span data-icon="chart"></span>
            Market trends
          </button>

          <p class="nav-label nav-label-spaced">Data</p>
          <button class="nav-item" data-view="apps">
            <span data-icon="grid"></span>
            Apps & tech
          </button>
          <button class="nav-item" data-view="products">
            <span data-icon="box"></span>
            Products
          </button>
        </nav>

        <div class="sidebar-card">
          <div class="mini-chart" aria-hidden="true">
            <i style="height: 32%"></i><i style="height: 46%"></i><i style="height: 38%"></i>
            <i style="height: 64%"></i><i style="height: 56%"></i><i style="height: 82%"></i>
            <i style="height: 100%"></i>
          </div>
          <p>Weekly data refresh</p>
          <span><?= number_format((int) $shopSignalData['stats']['updated_stores']) ?> stores updated</span>
        </div>

        <div class="user-block">
          <div class="avatar">AM</div>
          <div>
            <strong>Alex Morgan</strong>
            <span>Growth workspace</span>
          </div>
          <button class="icon-button" aria-label="Open profile menu" data-icon="more"></button>
        </div>
      </aside>

      <main class="main">
        <header class="topbar">
          <button class="icon-button mobile-menu" id="menuButton" aria-label="Toggle menu" data-icon="menu"></button>
          <div class="breadcrumb"><span>Workspace</span><b>/</b> Store explorer</div>
          <div class="top-actions">
            <button class="search-trigger" id="searchTrigger">
              <span data-icon="search"></span>
              <span>Search a store or domain</span>
              <kbd>⌘ K</kbd>
            </button>
            <button class="icon-button notification" aria-label="Notifications" data-icon="bell">
              <i></i>
            </button>
            <button class="button secondary" id="exportButton">
              <span data-icon="download"></span> Export
            </button>
          </div>
        </header>

        <section class="content" id="explorerView">
          <div class="page-heading">
            <div>
              <div class="eyebrow"><span></span> Shopify intelligence</div>
              <h1>Find your next best customer.</h1>
              <p>Search and segment 4.8M+ active Shopify stores by growth, technology, products, and market signals.</p>
            </div>
            <button class="button primary" id="saveViewButton">
              <span data-icon="plus"></span> Save this view
            </button>
          </div>

          <div class="metric-grid">
            <article class="metric-card">
              <div class="metric-top">
                <span>Matching stores</span>
                <span class="metric-icon violet" data-icon="store"></span>
              </div>
              <strong id="matchCount"><?= number_format((int) $shopSignalData['stats']['matching_stores']) ?></strong>
              <p><b>+12.4%</b> from last month</p>
            </article>
            <article class="metric-card">
              <div class="metric-top">
                <span>New this week</span>
                <span class="metric-icon green" data-icon="spark"></span>
              </div>
              <strong><?= number_format((int) $shopSignalData['stats']['new_this_week']) ?></strong>
              <p><b>+8.2%</b> weekly growth</p>
            </article>
            <article class="metric-card">
              <div class="metric-top">
                <span>Median revenue</span>
                <span class="metric-icon amber" data-icon="dollar"></span>
              </div>
              <strong><?= htmlspecialchars((string) $shopSignalData['stats']['median_revenue']) ?></strong>
              <p>Estimated monthly GMV</p>
            </article>
            <article class="metric-card">
              <div class="metric-top">
                <span>High-growth stores</span>
                <span class="metric-icon blue" data-icon="trending"></span>
              </div>
              <strong><?= number_format((int) $shopSignalData['stats']['high_growth_stores']) ?></strong>
              <p><b>14.5%</b> of this segment</p>
            </article>
          </div>

          <section class="explorer-card">
            <div class="filter-bar">
              <div class="filter-search">
                <span data-icon="search"></span>
                <input id="storeSearch" type="search" placeholder="Search within results..." autocomplete="off" />
              </div>
              <div class="filter-actions">
                <button class="button secondary" id="filterButton">
                  <span data-icon="sliders"></span> Filters <span class="filter-badge">4</span>
                </button>
                <button class="button secondary" id="columnsButton">
                  <span data-icon="columns"></span> Columns
                </button>
              </div>
            </div>

            <div class="chips" id="chips">
              <button class="clear-button" id="clearFilters">Clear all</button>
            </div>

            <div class="saved-views" id="savedViews">
              <span>Saved views</span>
              <div class="saved-view-list" id="savedViewList"></div>
            </div>

            <div class="results-header">
              <div>
                <strong id="resultsLabel"><?= number_format((int) $shopSignalData['stats']['matching_stores']) ?> stores</strong>
                <span><?= $databaseConnected ? 'Live database connection' : 'Using sample data' ?></span>
              </div>
              <label class="sort-control">
                Sort by
                <select id="sortSelect">
                  <option value="signal">Growth signal</option>
                  <option value="revenue">Est. revenue</option>
                  <option value="traffic">Monthly traffic</option>
                  <option value="newest">Newest first</option>
                  <option value="products">Product count</option>
                </select>
              </label>
            </div>

            <div class="table-wrap">
              <table>
                <thead>
                  <tr>
                    <th class="check-cell"><input type="checkbox" id="selectAll" aria-label="Select all stores" /></th>
                    <th>Store</th>
                    <th>Category</th>
                    <th>Est. monthly revenue</th>
                    <th>Traffic</th>
                    <th>Growth signal</th>
                    <th>Stack</th>
                    <th></th>
                  </tr>
                </thead>
                <tbody id="storeTable"></tbody>
              </table>
              <div class="empty-state" id="emptyState">
                <span data-icon="search"></span>
                <h3>No stores found</h3>
                <p>Try a different store name, domain, or category.</p>
              </div>
              <div class="table-loading" id="tableLoading" aria-hidden="true">
                <span class="loading-spinner"></span>
                <strong>Loading stores…</strong>
                <small>Fetching the next database page</small>
              </div>
            </div>

            <div class="table-footer">
              <p><span id="pageStatus">Showing <?= count($shopSignalData['stores']) ?> stores</span></p>
              <div class="pagination" id="paginationControls" aria-label="Store pagination">
                <button class="icon-button" id="previousPage" aria-label="Previous page" data-icon="chevron-left" disabled></button>
                <div class="page-list" id="pageList"></div>
                <button class="icon-button" id="nextPage" aria-label="Next page" data-icon="chevron-right"></button>
              </div>
            </div>
          </section>
        </section>

        <section class="content signals-view" id="signalsView" style="display: none;">
          <div class="page-heading">
            <div>
              <div class="eyebrow"><span></span> Buying signals</div>
              <h1>Recent store activity.</h1>
              <p>Spot Shopify stores showing growth, product, traffic, social, and technology changes.</p>
            </div>
            <button class="button secondary" id="refreshSignalsButton">
              <span data-icon="activity"></span> Refresh signals
            </button>
          </div>

          <section class="signals-card">
            <div class="signals-toolbar">
              <div class="signal-filter-tabs" id="signalFilterTabs">
                <button class="signal-filter active" data-signal-type="all">All <span>0</span></button>
                <button class="signal-filter" data-signal-type="growth">Growth <span>0</span></button>
                <button class="signal-filter" data-signal-type="technology">Technology <span>0</span></button>
                <button class="signal-filter" data-signal-type="product">Product <span>0</span></button>
                <button class="signal-filter" data-signal-type="traffic">Traffic <span>0</span></button>
                <button class="signal-filter" data-signal-type="social">Social <span>0</span></button>
              </div>
            </div>

            <div class="signals-loading" id="signalsLoading">
              <span class="loading-spinner"></span>
              <strong>Loading signals…</strong>
            </div>
            <div class="signals-feed" id="signalsFeed"></div>
            <div class="saved-empty" id="signalsEmpty">
              <span data-icon="activity"></span>
              <h3>No signals found</h3>
              <p>Try another signal type or generate more demo store activity.</p>
            </div>
          </section>
        </section>

        <section class="content market-view" id="marketView" style="display: none;">
          <div class="page-heading">
            <div>
              <div class="eyebrow"><span></span> Market intelligence</div>
              <h1>Market trends.</h1>
              <p>Track category momentum, regional concentration, technology adoption, and growth pockets across the Shopify index.</p>
            </div>
            <button class="button secondary" id="refreshMarketButton">
              <span data-icon="chart"></span> Refresh trends
            </button>
          </div>

          <div class="metric-grid market-metrics" id="marketMetrics">
            <article class="metric-card"><div class="metric-top"><span>Total stores</span><span class="metric-icon violet" data-icon="store"></span></div><strong>—</strong><p>Indexed Shopify stores</p></article>
            <article class="metric-card"><div class="metric-top"><span>Avg. growth</span><span class="metric-icon green" data-icon="trending"></span></div><strong>—</strong><p>Across active stores</p></article>
            <article class="metric-card"><div class="metric-top"><span>Avg. revenue</span><span class="metric-icon amber" data-icon="dollar"></span></div><strong>—</strong><p>Estimated monthly GMV</p></article>
            <article class="metric-card"><div class="metric-top"><span>Total traffic</span><span class="metric-icon blue" data-icon="activity"></span></div><strong>—</strong><p>Monthly visits estimate</p></article>
          </div>

          <section class="market-grid">
            <article class="market-panel">
              <div class="section-heading"><h3>Category share</h3><span>By store count</span></div>
              <div class="trend-list" id="categoryTrendList"></div>
            </article>
            <article class="market-panel">
              <div class="section-heading"><h3>Fastest-growing categories</h3><span>Avg. growth</span></div>
              <div class="trend-list" id="growthTrendList"></div>
            </article>
            <article class="market-panel">
              <div class="section-heading"><h3>Top countries</h3><span>Store concentration</span></div>
              <div class="trend-list" id="countryTrendList"></div>
            </article>
            <article class="market-panel">
              <div class="section-heading"><h3>Technology adoption</h3><span>Detected apps</span></div>
              <div class="trend-list" id="technologyTrendList"></div>
            </article>
          </section>

          <div class="signals-loading" id="marketLoading">
            <span class="loading-spinner"></span>
            <strong>Loading market trends…</strong>
          </div>
        </section>

        <section class="content apps-view" id="appsView" style="display: none;">
          <div class="page-heading">
            <div>
              <div class="eyebrow"><span></span> Apps & technology</div>
              <h1>Technology intelligence.</h1>
              <p>Analyze detected Shopify apps, adoption by category, estimated spend, and stores using each technology.</p>
            </div>
            <button class="button secondary" id="refreshAppsButton">
              <span data-icon="grid"></span> Refresh apps
            </button>
          </div>

          <div class="metric-grid market-metrics" id="appsMetrics">
            <article class="metric-card"><div class="metric-top"><span>Detected apps</span><span class="metric-icon violet" data-icon="grid"></span></div><strong>—</strong><p>Total app detections</p></article>
            <article class="metric-card"><div class="metric-top"><span>Stores with apps</span><span class="metric-icon green" data-icon="store"></span></div><strong>—</strong><p>Using at least one app</p></article>
            <article class="metric-card"><div class="metric-top"><span>Unique apps</span><span class="metric-icon blue" data-icon="box"></span></div><strong>—</strong><p>Distinct technologies</p></article>
            <article class="metric-card"><div class="metric-top"><span>Avg. app cost</span><span class="metric-icon amber" data-icon="dollar"></span></div><strong>—</strong><p>Estimated monthly app cost</p></article>
          </div>

          <section class="apps-layout">
            <article class="market-panel">
              <div class="section-heading"><h3>Top detected apps</h3><span>Click to inspect stores</span></div>
              <div class="apps-list" id="appsList"></div>
            </article>
            <article class="market-panel">
              <div class="section-heading"><h3>App categories</h3><span>Adoption by category</span></div>
              <div class="trend-list" id="appCategoryList"></div>
            </article>
          </section>

          <section class="signals-card app-store-panel">
            <div class="results-header">
              <div>
                <strong id="selectedAppTitle">Stores using this app</strong>
                <span id="selectedAppSubtitle">Top stores by estimated revenue</span>
              </div>
            </div>
            <div class="saved-store-grid" id="appStoreGrid"></div>
            <div class="signals-loading" id="appsLoading">
              <span class="loading-spinner"></span>
              <strong>Loading technology data…</strong>
            </div>
          </section>
        </section>

        <section class="content products-view" id="productsView" style="display: none;">
          <div class="page-heading">
            <div>
              <div class="eyebrow"><span></span> Product intelligence</div>
              <h1>Products & catalog trends.</h1>
              <p>Explore detected products, category depth, average prices, and the stores behind winning catalog items.</p>
            </div>
            <button class="button secondary" id="refreshProductsButton">
              <span data-icon="box"></span> Refresh products
            </button>
          </div>

          <div class="metric-grid market-metrics" id="productsMetrics">
            <article class="metric-card"><div class="metric-top"><span>Detected products</span><span class="metric-icon violet" data-icon="box"></span></div><strong>—</strong><p>Total product records</p></article>
            <article class="metric-card"><div class="metric-top"><span>Stores with products</span><span class="metric-icon green" data-icon="store"></span></div><strong>—</strong><p>Stores with catalog data</p></article>
            <article class="metric-card"><div class="metric-top"><span>Categories</span><span class="metric-icon blue" data-icon="grid"></span></div><strong>—</strong><p>Distinct product categories</p></article>
            <article class="metric-card"><div class="metric-top"><span>Avg. price</span><span class="metric-icon amber" data-icon="dollar"></span></div><strong>—</strong><p>Across detected products</p></article>
          </div>

          <section class="apps-layout products-layout">
            <article class="market-panel">
              <div class="section-heading"><h3>Product categories</h3><span>Click to inspect products</span></div>
              <div class="apps-list" id="productCategoryList"></div>
            </article>
            <article class="market-panel">
              <div class="section-heading"><h3>Top products</h3><span>Highest priced featured products</span></div>
              <div class="product-list compact" id="topProductList"></div>
            </article>
          </section>

          <section class="signals-card app-store-panel">
            <div class="results-header">
              <div>
                <strong id="selectedProductCategoryTitle">Products in this category</strong>
                <span id="selectedProductCategorySubtitle">Top products with store context</span>
              </div>
            </div>
            <div class="product-list product-grid" id="categoryProductList"></div>
            <div class="signals-loading" id="productsLoading">
              <span class="loading-spinner"></span>
              <strong>Loading product data…</strong>
            </div>
          </section>
        </section>

        <section class="content lists-view" id="listsView" style="display: none;">
          <div class="page-heading">
            <div>
              <div class="eyebrow"><span></span> Prospect workspace</div>
              <h1>Saved lists.</h1>
              <p>Keep promising Shopify stores in focused prospect lists for outreach, research, and export.</p>
            </div>
            <button class="button primary" id="createListButton">
              <span data-icon="plus"></span> New list
            </button>
          </div>

          <section class="saved-layout">
            <aside class="saved-sidebar">
              <div class="section-heading">
                <h3>Your lists</h3>
                <span id="savedListTotal">0 lists</span>
              </div>
              <div class="saved-list-nav" id="savedListsNav"></div>
            </aside>

            <div class="saved-main">
              <div class="results-header">
                <div>
                  <strong id="savedListTitle">Prospects</strong>
                  <span id="savedListSubtitle">Stores you saved from the explorer</span>
                </div>
                <div class="saved-header-actions">
                  <button class="button secondary" id="exportSavedList">Export list</button>
                  <button class="button secondary" id="refreshSavedLists">Refresh</button>
                </div>
              </div>

              <div class="saved-empty" id="savedEmpty">
                <span data-icon="bookmark"></span>
                <h3>No saved stores yet</h3>
                <p>Open a store in the explorer and click “Add to list”.</p>
                <button class="button primary" id="savedBackToExplorer">Go to explorer</button>
              </div>

              <div class="saved-store-grid" id="savedStoreGrid"></div>
            </div>
          </section>
        </section>

        <section class="placeholder-view" id="placeholderView">
          <span class="placeholder-icon" data-icon="spark"></span>
          <p class="eyebrow">ShopSignal workspace</p>
          <h2 id="placeholderTitle">Saved lists</h2>
          <p>This area is ready for the next part of the product experience.</p>
          <button class="button primary" id="backToExplorer">Return to explorer</button>
        </section>
      </main>
    </div>

    <div class="drawer-backdrop" id="drawerBackdrop"></div>
    <aside class="filter-drawer" id="filterDrawer" aria-hidden="true">
      <div class="drawer-header">
        <div>
          <p class="eyebrow">Refine audience</p>
          <h2>Filters</h2>
        </div>
        <button class="icon-button" id="closeFilters" aria-label="Close filters" data-icon="close"></button>
      </div>
      <div class="drawer-body">
        <div class="filter-group">
          <label for="categoryFilter">Store category</label>
          <input id="categoryFilter" class="filter-input" placeholder="Beauty, Apparel, Home…" />
        </div>
        <div class="filter-group">
          <label for="countrySelect">Headquarters</label>
          <select id="countrySelect">
            <option value="">Any country</option>
            <option>United States</option>
            <option>Canada</option>
            <option>United Kingdom</option>
            <option>Australia</option>
          </select>
        </div>
        <div class="filter-group">
          <label for="minRevenueFilter">Minimum monthly revenue</label>
          <select id="minRevenueFilter">
            <option value="0">Any revenue</option>
            <option value="50000">$50k+</option>
            <option value="100000">$100k+</option>
            <option value="250000">$250k+</option>
            <option value="500000">$500k+</option>
            <option value="1000000">$1m+</option>
          </select>
        </div>
        <div class="filter-group">
          <label for="minGrowthFilter">Minimum growth</label>
          <select id="minGrowthFilter">
            <option value="0">Any growth</option>
            <option value="5">5%+</option>
            <option value="10">10%+</option>
            <option value="15">15%+</option>
            <option value="25">25%+</option>
          </select>
        </div>
        <div class="filter-group">
          <label for="technologyFilter">Technology</label>
          <input id="technologyFilter" class="filter-input" placeholder="Klaviyo, Recharge, Gorgias…" />
        </div>
        <div class="filter-group">
          <label for="productCategoryFilter">Product category</label>
          <input id="productCategoryFilter" class="filter-input" placeholder="Skincare, Footwear, Bedding…" />
        </div>
      </div>
      <div class="drawer-footer">
        <button class="button secondary" id="resetDrawer">Reset</button>
        <button class="button primary" id="applyFilters">Show <?= number_format((int) $shopSignalData['stats']['matching_stores']) ?> stores</button>
      </div>
    </aside>

    <div class="modal-backdrop" id="searchModal">
      <div class="command-modal">
        <div class="command-input">
          <span data-icon="search"></span>
          <input id="globalSearch" placeholder="Search any Shopify store or domain..." />
          <kbd>ESC</kbd>
        </div>
        <div class="command-content">
          <p class="command-label">Suggested stores</p>
          <button class="command-result" data-search="Allbirds">
            <span class="store-logo logo-allbirds">A</span>
            <span><strong>Allbirds</strong><small>allbirds.com · Footwear</small></span>
            <span data-icon="arrow-up-right"></span>
          </button>
          <button class="command-result" data-search="Gymshark">
            <span class="store-logo logo-gymshark">G</span>
            <span><strong>Gymshark</strong><small>gymshark.com · Apparel</small></span>
            <span data-icon="arrow-up-right"></span>
          </button>
          <button class="command-result" data-search="Brooklinen">
            <span class="store-logo logo-brooklinen">B</span>
            <span><strong>Brooklinen</strong><small>brooklinen.com · Home</small></span>
            <span data-icon="arrow-up-right"></span>
          </button>
        </div>
        <div class="command-footer"><span><kbd>↑</kbd><kbd>↓</kbd> to navigate</span><span><kbd>↵</kbd> to open</span></div>
      </div>
    </div>

    <aside class="detail-panel" id="detailPanel" aria-hidden="true">
      <div class="detail-header">
        <button class="icon-button" id="closeDetail" aria-label="Close store detail" data-icon="close"></button>
      </div>
      <div id="detailContent"></div>
    </aside>
    <div class="detail-backdrop" id="detailBackdrop"></div>

    <div class="toast" id="toast">
      <span data-icon="check"></span>
      <div><strong id="toastTitle">View saved</strong><p id="toastMessage">You can find it in Saved lists.</p></div>
    </div>

    <script>
      window.SHOPSIGNAL_DATA = <?= json_encode(
        [
          'stores' => $shopSignalData['stores'],
          'profiles' => $shopSignalData['profiles'],
          'stats' => $shopSignalData['stats'],
          'source' => $shopSignalData['source'],
        ],
        JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES
      ) ?>;
      window.SHOPSIGNAL_CONFIG = {
        basePath: <?= json_encode(shopSignalBasePath(), JSON_HEX_TAG | JSON_HEX_AMP) ?>,
        apiUrl: <?= json_encode(shopSignalAssetUrl('api/stores.php'), JSON_HEX_TAG | JSON_HEX_AMP) ?>,
        storeApiUrl: <?= json_encode(shopSignalAssetUrl('api/store.php'), JSON_HEX_TAG | JSON_HEX_AMP) ?>,
        exportApiUrl: <?= json_encode(shopSignalAssetUrl('api/export.php'), JSON_HEX_TAG | JSON_HEX_AMP) ?>,
        segmentsApiUrl: <?= json_encode(shopSignalAssetUrl('api/segments.php'), JSON_HEX_TAG | JSON_HEX_AMP) ?>,
        listsApiUrl: <?= json_encode(shopSignalAssetUrl('api/lists.php'), JSON_HEX_TAG | JSON_HEX_AMP) ?>,
        signalsApiUrl: <?= json_encode(shopSignalAssetUrl('api/signals.php'), JSON_HEX_TAG | JSON_HEX_AMP) ?>,
        marketApiUrl: <?= json_encode(shopSignalAssetUrl('api/market.php'), JSON_HEX_TAG | JSON_HEX_AMP) ?>,
        appsApiUrl: <?= json_encode(shopSignalAssetUrl('api/apps.php'), JSON_HEX_TAG | JSON_HEX_AMP) ?>,
        productsApiUrl: <?= json_encode(shopSignalAssetUrl('api/products.php'), JSON_HEX_TAG | JSON_HEX_AMP) ?>
      };
    </script>
    <script src="<?= htmlspecialchars(shopSignalVersionedAssetUrl('app.js')) ?>"></script>
  </body>
</html>
