const icons = {
  search: '<svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg>',
  bookmark: '<svg viewBox="0 0 24 24"><path d="M6 4.8A1.8 1.8 0 0 1 7.8 3h8.4A1.8 1.8 0 0 1 18 4.8V21l-6-3.6L6 21V4.8Z"/></svg>',
  activity: '<svg viewBox="0 0 24 24"><path d="M3 12h4l2-7 4 14 2-7h6"/></svg>',
  chart: '<svg viewBox="0 0 24 24"><path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/></svg>',
  grid: '<svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>',
  box: '<svg viewBox="0 0 24 24"><path d="m4 7 8-4 8 4-8 4-8-4Z"/><path d="m4 7 8 4v10l-8-4V7ZM20 7l-8 4v10l8-4V7Z"/></svg>',
  more: '<svg viewBox="0 0 24 24"><circle cx="5" cy="12" r="1"/><circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/></svg>',
  menu: '<svg viewBox="0 0 24 24"><path d="M4 7h16M4 12h16M4 17h16"/></svg>',
  bell: '<svg viewBox="0 0 24 24"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9ZM10 21h4"/></svg>',
  download: '<svg viewBox="0 0 24 24"><path d="M12 3v12m0 0 4-4m-4 4-4-4M4 19h16"/></svg>',
  plus: '<svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>',
  store: '<svg viewBox="0 0 24 24"><path d="m4 10 1.4-5h13.2l1.4 5M5 10v9h14v-9"/><path d="M9 19v-5h6v5M3 10h18"/></svg>',
  spark: '<svg viewBox="0 0 24 24"><path d="m12 3 1.4 4.1L17.5 8.5l-4.1 1.4L12 14l-1.4-4.1-4.1-1.4 4.1-1.4L12 3ZM19 15l.7 2.3L22 18l-2.3.7L19 21l-.7-2.3L16 18l2.3-.7L19 15Z"/></svg>',
  dollar: '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M15 8.5c-.7-.6-1.7-1-3-1-1.7 0-3 1-3 2.3 0 3.4 6 1.4 6 4.4 0 1.3-1.3 2.3-3 2.3-1.3 0-2.5-.5-3.2-1.2M12 5v14"/></svg>',
  trending: '<svg viewBox="0 0 24 24"><path d="m3 17 6-6 4 4 7-8"/><path d="M15 7h5v5"/></svg>',
  sliders: '<svg viewBox="0 0 24 24"><path d="M4 7h10M18 7h2M4 17h2M10 17h10"/><circle cx="16" cy="7" r="2"/><circle cx="8" cy="17" r="2"/></svg>',
  columns: '<svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M9 4v16M15 4v16"/></svg>',
  "chevron-left": '<svg viewBox="0 0 24 24"><path d="m15 18-6-6 6-6"/></svg>',
  "chevron-right": '<svg viewBox="0 0 24 24"><path d="m9 18 6-6-6-6"/></svg>',
  close: '<svg viewBox="0 0 24 24"><path d="m6 6 12 12M18 6 6 18"/></svg>',
  "arrow-up-right": '<svg viewBox="0 0 24 24"><path d="M7 17 17 7M8 7h9v9"/></svg>',
  check: '<svg viewBox="0 0 24 24"><path d="m5 12 4 4L19 6"/></svg>',
  external: '<svg viewBox="0 0 24 24"><path d="M14 5h5v5M19 5l-9 9"/><path d="M19 14v4a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1h4"/></svg>'
};

let activeDetailStore = null;
let savedListsState = { lists: [], selected_list: null, stores: [], profiles: {} };
let signalsState = { type: "all", signals: [], profiles: {}, counts: {} };
let marketState = null;
let appsState = null;
let productsState = null;

document.querySelectorAll("[data-icon]").forEach((element) => {
  const name = element.dataset.icon;
  if (icons[name]) element.innerHTML = icons[name];
});

const sampleStores = [
  { name: "Allbirds", domain: "allbirds.com", category: "Footwear", revenue: 8200000, revenueLabel: "$8.2M", traffic: 2400000, trafficLabel: "2.4M", growth: 28.4, signal: "High", logo: "A", logoClass: "logo-allbirds", stack: ["kl", "go", "yo"], founded: "2016", products: "278" },
  { name: "Gymshark", domain: "gymshark.com", category: "Apparel", revenue: 12900000, revenueLabel: "$12.9M", traffic: 5100000, trafficLabel: "5.1M", growth: 23.1, signal: "High", logo: "G", logoClass: "logo-gymshark", stack: ["kl", "rc", "go", "+3"], founded: "2012", products: "1,842" },
  { name: "Brooklinen", domain: "brooklinen.com", category: "Home & Living", revenue: 4800000, revenueLabel: "$4.8M", traffic: 890000, trafficLabel: "890K", growth: 18.6, signal: "High", logo: "B", logoClass: "logo-brooklinen", stack: ["kl", "go", "ok"], founded: "2014", products: "412" },
  { name: "Glossier", domain: "glossier.com", category: "Beauty", revenue: 7100000, revenueLabel: "$7.1M", traffic: 1800000, trafficLabel: "1.8M", growth: 15.2, signal: "High", logo: "G", logoClass: "logo-glossier", stack: ["kl", "yo", "+2"], founded: "2014", products: "186" },
  { name: "Beardbrand", domain: "beardbrand.com", category: "Personal Care", revenue: 910000, revenueLabel: "$910K", traffic: 340000, trafficLabel: "340K", growth: 9.8, signal: "Medium", logo: "B", logoClass: "logo-beardbrand", stack: ["kl", "rc", "go"], founded: "2012", products: "94" },
  { name: "Kylie Cosmetics", domain: "kyliecosmetics.com", category: "Beauty", revenue: 3600000, revenueLabel: "$3.6M", traffic: 1200000, trafficLabel: "1.2M", growth: 8.3, signal: "Medium", logo: "K", logoClass: "logo-kylie", stack: ["kl", "yo", "+4"], founded: "2015", products: "324" }
];

const sampleStoreProfiles = {
  "Allbirds": {
    location: "San Francisco, CA", country: "United States", employees: "350–500",
    email: "help@allbirds.com", phone: "+1 888 963 8944", language: "English", currency: "USD",
    avgPrice: "$86", orders: "94.3k", conversion: "3.1%", social: "1.2M",
    instagram: "482k", tiktok: "211k", facebook: "530k",
    apps: [["Klaviyo", "Email marketing", "May 2021"], ["Yotpo", "Reviews & loyalty", "Sep 2020"], ["Gorgias", "Customer support", "Jan 2022"], ["Nosto", "Personalization", "Mar 2023"]],
    products: [["Tree Runner", "Footwear", "$98"], ["Wool Runner", "Footwear", "$110"], ["Tree Dasher 2", "Footwear", "$135"]],
    signals: [["2 days ago", "Traffic spike", "Organic traffic increased 18% week over week."], ["8 days ago", "New app detected", "Nosto personalization was added to the storefront."], ["21 days ago", "Catalog growth", "34 new products were published."]]
  },
  "Gymshark": {
    location: "Solihull, England", country: "United Kingdom", employees: "900–1,000",
    email: "support@gymshark.com", phone: "+44 121 728 2828", language: "English", currency: "USD / GBP",
    avgPrice: "$54", orders: "238k", conversion: "3.7%", social: "12.8M",
    instagram: "7.1M", tiktok: "5.4M", facebook: "1.9M",
    apps: [["Klaviyo", "Email marketing", "Feb 2020"], ["Recharge", "Subscriptions", "Jun 2022"], ["Gorgias", "Customer support", "Oct 2021"], ["Algolia", "Site search", "Apr 2023"]],
    products: [["Arrival 5” Shorts", "Menswear", "$30"], ["Vital Seamless Leggings", "Womenswear", "$60"], ["Crest Hoodie", "Menswear", "$50"]],
    signals: [["Yesterday", "Paid media growth", "Estimated ad spend rose 24% in the last 30 days."], ["6 days ago", "International expansion", "New localized storefront detected for South Korea."], ["14 days ago", "Hiring signal", "12 new ecommerce roles were posted."]]
  },
  "Brooklinen": {
    location: "Brooklyn, NY", country: "United States", employees: "150–250",
    email: "hello@brooklinen.com", phone: "+1 646 798 7447", language: "English", currency: "USD",
    avgPrice: "$118", orders: "40.7k", conversion: "2.8%", social: "704k",
    instagram: "414k", tiktok: "83k", facebook: "207k",
    apps: [["Klaviyo", "Email marketing", "Aug 2019"], ["Okendo", "Reviews", "Nov 2021"], ["Gorgias", "Customer support", "Apr 2020"], ["Rebuy", "Upsells", "Jul 2023"]],
    products: [["Luxe Core Sheet Set", "Bedding", "$189"], ["Super-Plush Bath Towels", "Bath", "$89"], ["Down Comforter", "Bedding", "$299"]],
    signals: [["3 days ago", "Best-seller velocity", "Top bedding products gained 16% in review velocity."], ["11 days ago", "New technology", "Rebuy was added for post-purchase offers."], ["26 days ago", "Promotion detected", "A sitewide seasonal campaign went live."]]
  },
  "Glossier": {
    location: "New York, NY", country: "United States", employees: "200–350",
    email: "gteam@glossier.com", phone: "+1 855 929 2179", language: "English", currency: "USD",
    avgPrice: "$31", orders: "229k", conversion: "4.2%", social: "4.1M",
    instagram: "3.1M", tiktok: "840k", facebook: "172k",
    apps: [["Klaviyo", "Email marketing", "Mar 2020"], ["Yotpo", "Reviews & loyalty", "Jul 2021"], ["Nosto", "Personalization", "Feb 2022"], ["Loop Returns", "Returns", "Sep 2022"]],
    products: [["Boy Brow", "Makeup", "$18"], ["Glossier You", "Fragrance", "$78"], ["Cloud Paint", "Makeup", "$22"]],
    signals: [["Today", "Product launch", "A new Cloud Paint shade collection was published."], ["5 days ago", "Traffic acceleration", "Direct traffic increased 22% month over month."], ["18 days ago", "Technology change", "Checkout personalization scripts were updated."]]
  },
  "Beardbrand": {
    location: "Austin, TX", country: "United States", employees: "20–50",
    email: "support@beardbrand.com", phone: "+1 844 662 3273", language: "English", currency: "USD",
    avgPrice: "$38", orders: "24.1k", conversion: "2.6%", social: "2.3M",
    instagram: "190k", tiktok: "94k", facebook: "145k",
    apps: [["Klaviyo", "Email marketing", "Jun 2018"], ["Recharge", "Subscriptions", "Jan 2020"], ["Gorgias", "Customer support", "Aug 2021"], ["Stamped", "Reviews", "May 2019"]],
    products: [["Utility Beard Oil", "Grooming", "$36"], ["Sea Salt Spray", "Hair", "$22"], ["Utility Balm", "Grooming", "$42"]],
    signals: [["4 days ago", "Subscription growth", "Subscription messaging became more prominent onsite."], ["15 days ago", "Catalog update", "Six product bundles were refreshed."], ["29 days ago", "Email activity", "Campaign frequency increased by 13%."]]
  },
  "Kylie Cosmetics": {
    location: "Los Angeles, CA", country: "United States", employees: "100–200",
    email: "customerservice@kyliecosmetics.com", phone: "+1 833 545 9543", language: "English", currency: "USD",
    avgPrice: "$29", orders: "124k", conversion: "3.4%", social: "31.2M",
    instagram: "25.7M", tiktok: "3.8M", facebook: "1.7M",
    apps: [["Klaviyo", "Email marketing", "Nov 2020"], ["Yotpo", "Reviews & loyalty", "Feb 2021"], ["Gorgias", "Customer support", "Aug 2022"], ["Afterpay", "Payments", "Jun 2020"]],
    products: [["Lip Kit", "Makeup", "$35"], ["Kylash Mascara", "Makeup", "$24"], ["Cosmic Eau de Parfum", "Fragrance", "$48"]],
    signals: [["Yesterday", "Social momentum", "TikTok mentions increased 31% this week."], ["9 days ago", "Product launch", "A limited lip collection was added."], ["23 days ago", "Promotional change", "Free shipping threshold decreased."]]
  }
};

let stores = Array.isArray(window.SHOPSIGNAL_DATA?.stores) && window.SHOPSIGNAL_DATA.stores.length
  ? window.SHOPSIGNAL_DATA.stores
  : sampleStores;
let storeProfiles = window.SHOPSIGNAL_DATA?.profiles && Object.keys(window.SHOPSIGNAL_DATA.profiles).length
  ? window.SHOPSIGNAL_DATA.profiles
  : sampleStoreProfiles;
const appConfig = window.SHOPSIGNAL_CONFIG || {};
const isDatabaseSource = window.SHOPSIGNAL_DATA?.source === "database";
const pageSize = 20;

const table = document.getElementById("storeTable");
const tableWrap = table?.closest(".table-wrap");
const emptyState = document.getElementById("emptyState");
const visibleCount = document.getElementById("visibleCount");
const resultsLabel = document.getElementById("resultsLabel");
const pageStatus = document.getElementById("pageStatus");
const pageList = document.getElementById("pageList");
const paginationControls = document.getElementById("paginationControls");
const previousPageButton = document.getElementById("previousPage");
const nextPageButton = document.getElementById("nextPage");
let currentStores = [...stores];
let currentTotal = Number(window.SHOPSIGNAL_DATA?.stats?.matching_stores || stores.length);
let currentPage = 1;
let isLoadingStores = false;
let searchDebounceTimer;

function escapeHtml(value) {
  return String(value ?? "")
    .replaceAll("&", "&amp;")
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll('"', "&quot;")
    .replaceAll("'", "&#039;");
}

function formatNumber(value) {
  return Number(value || 0).toLocaleString();
}

function updateResultCounts() {
  if (visibleCount) visibleCount.textContent = formatNumber(currentStores.length);
  resultsLabel.textContent = `${formatNumber(currentTotal)} stores`;
  const start = currentStores.length ? ((currentPage - 1) * pageSize) + 1 : 0;
  const end = Math.min(currentPage * pageSize, currentTotal);
  pageStatus.textContent = currentStores.length
    ? `Showing ${formatNumber(start)}–${formatNumber(end)} of ${formatNumber(currentTotal)} stores`
    : "No stores found";
  renderPagination();
}

function setTableLoading(loading) {
  tableWrap?.classList.toggle("loading", loading);
  document.getElementById("tableLoading")?.setAttribute("aria-hidden", String(!loading));
}

function paginationRange(current, total) {
  if (total <= 7) return Array.from({ length: total }, (_, index) => index + 1);

  const pages = [1];
  const start = Math.max(2, current - 1);
  const end = Math.min(total - 1, current + 1);

  if (start > 2) pages.push("…");
  for (let page = start; page <= end; page += 1) pages.push(page);
  if (end < total - 1) pages.push("…");
  pages.push(total);

  return pages;
}

function renderPagination() {
  if (!pageList || !previousPageButton || !nextPageButton) return;

  const totalPages = Math.max(1, Math.ceil(currentTotal / pageSize));
  previousPageButton.disabled = isLoadingStores || currentPage <= 1;
  nextPageButton.disabled = isLoadingStores || currentPage >= totalPages;

  pageList.innerHTML = paginationRange(currentPage, totalPages).map((page) => {
    if (page === "…") return `<span class="page-ellipsis">…</span>`;
    return `<button class="page ${page === currentPage ? "active" : ""}" data-page="${page}">${page}</button>`;
  }).join("");
}

function fallbackProfile(store) {
  return {
    location: "Unknown",
    country: "Unknown",
    employees: "Unknown",
    email: `hello@${store.domain}`,
    phone: "Not available",
    language: "English",
    currency: "USD",
    avgPrice: "$0",
    orders: "0",
    conversion: "0%",
    social: "0",
    instagram: "0",
    tiktok: "0",
    facebook: "0",
    apps: [],
    products: [],
    signals: [],
  };
}

function stackHtml(stack) {
  return (Array.isArray(stack) ? stack : []).map((item) => {
    if (item.startsWith("+")) return `<span class="tech more-tech">${item}</span>`;
    const labels = { kl: "K", rc: "R", go: "G", yo: "Y", ok: "O" };
    return `<span class="tech ${escapeHtml(item)}">${labels[item] || escapeHtml(item.charAt(0).toUpperCase())}</span>`;
  }).join("");
}

function renderStores(items) {
  table.innerHTML = items.map((store, index) => `
    <tr data-store-id="${escapeHtml(store.id)}">
      <td class="check-cell"><input type="checkbox" class="row-check" aria-label="Select ${escapeHtml(store.name)}" /></td>
      <td>
        <div class="store-cell">
          <span class="store-logo ${escapeHtml(store.logoClass)}">${escapeHtml(store.logo)}</span>
          <span class="store-meta"><strong>${escapeHtml(store.name)}</strong><span>${escapeHtml(store.domain)}</span></span>
        </div>
      </td>
      <td><span class="category-pill">${escapeHtml(store.category)}</span></td>
      <td class="revenue"><strong>${escapeHtml(store.revenueLabel)}</strong><span>Shopify Plus</span></td>
      <td class="traffic">${escapeHtml(store.trafficLabel)}</td>
      <td><span class="signal ${store.signal === "Medium" ? "medium" : ""}">↗ ${escapeHtml(store.growth)}%</span></td>
      <td><div class="tech-stack">${stackHtml(store.stack)}</div></td>
      <td><button class="icon-button row-action" aria-label="Open ${escapeHtml(store.name)} details" data-row="${index}">${icons.more}</button></td>
    </tr>
  `).join("");
  emptyState.style.display = items.length ? "none" : "block";
  table.closest("table").style.display = items.length ? "table" : "none";
  updateResultCounts();
}
renderStores(currentStores);

const searchInput = document.getElementById("storeSearch");
const filterInputs = {
  category: document.getElementById("categoryFilter"),
  country: document.getElementById("countrySelect"),
  min_revenue: document.getElementById("minRevenueFilter"),
  min_growth: document.getElementById("minGrowthFilter"),
  technology: document.getElementById("technologyFilter"),
  product_category: document.getElementById("productCategoryFilter"),
};
let activeFilters = {};
let savedSegmentsState = [];

function collectFilters() {
  return Object.fromEntries(Object.entries(filterInputs)
    .map(([key, input]) => [key, input?.value?.trim?.() || ""])
    .filter(([, value]) => value !== "" && value !== "0"));
}

function setFilterInputValue(key, value = "") {
  if (filterInputs[key]) filterInputs[key].value = value;
}

function filterLabel(key, value) {
  const labels = {
    category: `Category: ${value}`,
    country: value,
    min_revenue: `Revenue: $${formatNumber(value)}+`,
    min_growth: `Growth: ${value}%+`,
    technology: `Tech: ${value}`,
    product_category: `Products: ${value}`,
  };
  return labels[key] || value;
}

function renderFilterChips() {
  const chips = document.getElementById("chips");
  if (!chips) return;

  const chipHtml = Object.entries(activeFilters).map(([key, value]) => `
    <button class="chip" data-filter-chip="${escapeHtml(key)}">${escapeHtml(filterLabel(key, value))} <span>×</span></button>
  `).join("");
  chips.innerHTML = `${chipHtml}<button class="clear-button" id="clearFilters">Clear all</button>`;
  updateFilterBadge();
}

function explorerQueryParams(extra = {}) {
  const params = new URLSearchParams({
    q: searchInput.value.trim(),
    sort: document.getElementById("sortSelect").value,
    ...extra
  });
  Object.entries(activeFilters).forEach(([key, value]) => params.set(key, value));
  return params;
}

async function fetchSavedSegments() {
  if (!appConfig.segmentsApiUrl) return null;
  const response = await fetch(appConfig.segmentsApiUrl, {
    headers: { Accept: "application/json" }
  });
  if (!response.ok) throw new Error("Saved views API failed");
  return response.json();
}

async function postSavedSegmentAction(payload) {
  if (!appConfig.segmentsApiUrl) return null;
  const response = await fetch(appConfig.segmentsApiUrl, {
    method: "POST",
    headers: {
      Accept: "application/json",
      "Content-Type": "application/json"
    },
    body: JSON.stringify(payload)
  });
  if (!response.ok) throw new Error("Saved views API failed");
  return response.json();
}

function renderSavedSegments(payload) {
  savedSegmentsState = Array.isArray(payload?.segments) ? payload.segments : [];
  const wrapper = document.getElementById("savedViews");
  const list = document.getElementById("savedViewList");
  if (!wrapper || !list) return;

  wrapper.classList.toggle("show", savedSegmentsState.length > 0);
  list.innerHTML = savedSegmentsState.map((segment) => `
    <button class="saved-view" data-segment-id="${segment.id}" title="${escapeHtml(segment.updated_label || "")}">
      ${escapeHtml(segment.name)}
      <span class="saved-view-delete" data-delete-segment-id="${segment.id}" aria-label="Delete ${escapeHtml(segment.name)}">×</span>
    </button>
  `).join("");
}

async function loadSavedSegments() {
  try {
    const payload = await fetchSavedSegments();
    if (payload?.ok) renderSavedSegments(payload);
  } catch {
    showToast("Could not load saved views", "The saved views API did not respond.");
  }
}

function applySavedSegment(segment) {
  searchInput.value = segment.search || "";
  document.getElementById("sortSelect").value = segment.sort || "growth";
  activeFilters = segment.filters && typeof segment.filters === "object" ? segment.filters : {};
  Object.keys(filterInputs).forEach((key) => setFilterInputValue(key, activeFilters[key] || ""));
  renderFilterChips();
  if (isDatabaseSource) loadStores(1);
  else searchInput.dispatchEvent(new Event("input"));
  showToast("Saved view loaded", segment.name);
}

searchInput.addEventListener("input", () => {
  if (isDatabaseSource) {
    clearTimeout(searchDebounceTimer);
    searchDebounceTimer = setTimeout(() => loadStores(1), 260);
    return;
  }

  const query = searchInput.value.trim().toLowerCase();
  currentStores = stores.filter((store) =>
    [store.name, store.domain, store.category].some((value) => value.toLowerCase().includes(query))
  );
  currentTotal = currentStores.length;
  renderStores(currentStores);
});

document.getElementById("sortSelect").addEventListener("change", (event) => {
  if (isDatabaseSource) {
    loadStores(1);
    return;
  }

  const type = event.target.value;
  currentStores.sort((a, b) => {
    if (type === "revenue") return b.revenue - a.revenue;
    if (type === "traffic") return b.traffic - a.traffic;
    if (type === "newest") return Number(b.founded) - Number(a.founded);
    return b.growth - a.growth;
  });
  renderStores(currentStores);
});

async function loadStores(page = 1) {
  if (!appConfig.apiUrl || isLoadingStores) return;

  const totalPages = Math.max(1, Math.ceil(currentTotal / pageSize));
  const requestedPage = Math.max(1, Math.min(page, totalPages));
  const offset = (requestedPage - 1) * pageSize;

  isLoadingStores = true;
  setTableLoading(true);
  updateResultCounts();

  const params = explorerQueryParams({
    limit: String(pageSize),
    offset: String(offset)
  });

  try {
    const response = await fetch(`${appConfig.apiUrl}?${params.toString()}`, {
      headers: { Accept: "application/json" }
    });

    if (!response.ok) throw new Error("Store API failed");

    const payload = await response.json();
    const incomingStores = Array.isArray(payload.data) ? payload.data : [];
    const incomingProfiles = payload.profiles && typeof payload.profiles === "object" ? payload.profiles : {};
    const pagination = payload.meta?.pagination || {};

    stores = incomingStores;
    currentStores = [...incomingStores];
    storeProfiles = { ...storeProfiles, ...incomingProfiles };
    currentTotal = Number(pagination.total ?? currentStores.length);
    currentPage = Math.floor(Number(pagination.offset ?? offset) / pageSize) + 1;
    renderStores(currentStores);
  } catch (error) {
    showToast("Could not load stores", "The database API did not respond. Try refreshing the page.");
  } finally {
    isLoadingStores = false;
    setTableLoading(false);
    updateResultCounts();
  }
}

if (paginationControls) {
  paginationControls.addEventListener("click", (event) => {
    const previousButton = event.target.closest("#previousPage");
    if (previousButton && !previousButton.disabled) {
      loadStores(currentPage - 1);
      return;
    }

    const nextButton = event.target.closest("#nextPage");
    if (nextButton && !nextButton.disabled) {
      loadStores(currentPage + 1);
      return;
    }

    const pageButton = event.target.closest("[data-page]");
    if (!pageButton) return;
    loadStores(Number(pageButton.dataset.page));
  });
}

async function fetchSavedLists(listId) {
  if (!appConfig.listsApiUrl) return null;

  const params = new URLSearchParams();
  if (listId) params.set("list_id", String(listId));

  const response = await fetch(`${appConfig.listsApiUrl}?${params.toString()}`, {
    headers: { Accept: "application/json" }
  });
  if (!response.ok) throw new Error("Saved lists API failed");
  return response.json();
}

async function postSavedListAction(payload) {
  if (!appConfig.listsApiUrl) return null;

  const response = await fetch(appConfig.listsApiUrl, {
    method: "POST",
    headers: {
      Accept: "application/json",
      "Content-Type": "application/json"
    },
    body: JSON.stringify(payload)
  });
  if (!response.ok) throw new Error("Saved lists API failed");
  return response.json();
}

function renderSavedLists(payload = savedListsState) {
  savedListsState = {
    lists: Array.isArray(payload.lists) ? payload.lists : [],
    selected_list: payload.selected_list || null,
    stores: Array.isArray(payload.stores) ? payload.stores : [],
    profiles: payload.profiles || {},
  };

  const listsNav = document.getElementById("savedListsNav");
  const savedListTitle = document.getElementById("savedListTitle");
  const savedListSubtitle = document.getElementById("savedListSubtitle");
  const savedListTotal = document.getElementById("savedListTotal");
  const savedListNavCount = document.getElementById("savedListNavCount");
  const savedEmpty = document.getElementById("savedEmpty");
  const savedStoreGrid = document.getElementById("savedStoreGrid");
  const selectedId = savedListsState.selected_list?.id;
  const totalSaved = savedListsState.lists.reduce((sum, list) => sum + Number(list.store_count || 0), 0);

  if (savedListNavCount) savedListNavCount.textContent = formatNumber(totalSaved);
  if (savedListTotal) savedListTotal.textContent = `${formatNumber(savedListsState.lists.length)} lists`;
  if (savedListTitle) savedListTitle.textContent = savedListsState.selected_list?.name || "Prospects";
  if (savedListSubtitle) {
    const count = Number(savedListsState.selected_list?.store_count || savedListsState.stores.length);
    savedListSubtitle.textContent = `${formatNumber(count)} saved ${count === 1 ? "store" : "stores"}`;
  }

  if (listsNav) {
    listsNav.innerHTML = savedListsState.lists.map((list) => `
      <button class="saved-list-item ${Number(list.id) === Number(selectedId) ? "active" : ""}" data-list-id="${list.id}">
        <span><strong>${escapeHtml(list.name)}</strong><span>${escapeHtml(list.description || "Saved Shopify stores")}</span></span>
        <small>${formatNumber(list.store_count)}</small>
      </button>
    `).join("");
  }

  if (savedEmpty) savedEmpty.classList.toggle("show", savedListsState.stores.length === 0);
  if (savedStoreGrid) {
    savedStoreGrid.style.display = savedListsState.stores.length ? "grid" : "none";
    savedStoreGrid.innerHTML = savedListsState.stores.map((store) => `
      <article class="saved-store-card" data-store-id="${store.id}">
        <div class="saved-store-card-top">
          <div class="store-cell">
            <span class="store-logo ${escapeHtml(store.logoClass)}">${escapeHtml(store.logo)}</span>
            <span class="store-meta"><strong>${escapeHtml(store.name)}</strong><span>${escapeHtml(store.domain)}</span></span>
          </div>
          <span class="category-pill">${escapeHtml(store.category)}</span>
        </div>
        <div class="saved-store-metrics">
          <div><span>Revenue</span><strong>${escapeHtml(store.revenueLabel)}</strong></div>
          <div><span>Traffic</span><strong>${escapeHtml(store.trafficLabel)}</strong></div>
          <div><span>Growth</span><strong>${escapeHtml(store.growth)}%</strong></div>
        </div>
        <div class="saved-card-actions">
          <button class="button secondary" data-saved-action="open" data-store-id="${store.id}">Open</button>
          <button class="button secondary" data-saved-action="remove" data-store-id="${store.id}">Remove</button>
        </div>
      </article>
    `).join("");
  }
}

async function loadSavedLists(listId) {
  const savedStoreGrid = document.getElementById("savedStoreGrid");
  const savedEmpty = document.getElementById("savedEmpty");

  try {
    if (savedStoreGrid) {
      savedStoreGrid.style.display = "grid";
      savedStoreGrid.innerHTML = `
        <article class="saved-store-card">
          <div class="table-loading-inline">
            <span class="loading-spinner"></span>
            <strong>Loading saved stores…</strong>
          </div>
        </article>
      `;
    }
    savedEmpty?.classList.remove("show");

    const payload = await fetchSavedLists(listId);
    if (payload?.ok) renderSavedLists(payload);
  } catch {
    showToast("Could not load saved lists", "The saved list API did not respond.");
  }
}

async function saveActiveStoreToList() {
  if (!activeDetailStore?.id) {
    showToast("No store selected", "Open a store before adding it to a list.");
    return;
  }

  try {
    const payload = await postSavedListAction({
      action: "add_store",
      store_id: activeDetailStore.id,
      list_id: savedListsState.selected_list?.id
    });
    if (payload?.ok) {
      renderSavedLists(payload);
      const listName = payload.selected_list?.name || "Prospects";
      showToast("Store added", `${activeDetailStore.name} was added to ${listName}.`);
    }
  } catch {
    showToast("Could not save store", "The saved list API did not respond.");
  }
}

function signalTypeIcon(type) {
  const iconsByType = {
    growth: "↗",
    technology: "T",
    product: "P",
    traffic: "V",
    social: "S",
  };
  return iconsByType[type] || "•";
}

function renderSignals(payload = signalsState) {
  signalsState = {
    type: payload.meta?.type || signalsState.type || "all",
    signals: Array.isArray(payload.signals) ? payload.signals : [],
    profiles: payload.profiles || {},
    counts: payload.meta?.counts || {},
  };

  document.querySelectorAll("[data-signal-type]").forEach((button) => {
    const type = button.dataset.signalType;
    button.classList.toggle("active", type === signalsState.type);
    const count = signalsState.counts[type] ?? 0;
    const badge = button.querySelector("span");
    if (badge) badge.textContent = formatNumber(count);
  });

  const feed = document.getElementById("signalsFeed");
  const empty = document.getElementById("signalsEmpty");
  if (empty) empty.classList.toggle("show", signalsState.signals.length === 0);
  if (!feed) return;

  feed.style.display = signalsState.signals.length ? "grid" : "none";
  feed.innerHTML = signalsState.signals.map((signal) => {
    const store = signal.store || {};
    return `
      <article class="signal-card" data-store-id="${escapeHtml(store.id)}">
        <span class="signal-type-icon">${escapeHtml(signalTypeIcon(signal.type))}</span>
        <div>
          <div class="signal-card-meta">
            <span class="signal-type-badge">${escapeHtml(signal.type)}</span>
            <small>${escapeHtml(signal.occurred_label || signal.occurred_at_label)}</small>
          </div>
          <h3>${escapeHtml(signal.title)}</h3>
          <p>${escapeHtml(signal.description)}</p>
          <div class="signal-store-link">
            <span class="store-logo ${escapeHtml(store.logoClass)}">${escapeHtml(store.logo)}</span>
            <span>${escapeHtml(store.name)} · ${escapeHtml(store.domain)}</span>
          </div>
        </div>
        <div class="signal-actions">
          <button class="button secondary" data-signal-action="open" data-store-id="${escapeHtml(store.id)}">Open</button>
          <button class="button primary" data-signal-action="save" data-store-id="${escapeHtml(store.id)}">Save</button>
        </div>
      </article>
    `;
  }).join("");
}

async function loadSignals(type = signalsState.type || "all") {
  const loading = document.getElementById("signalsLoading");
  const feed = document.getElementById("signalsFeed");
  const empty = document.getElementById("signalsEmpty");

  try {
    loading?.classList.add("show");
    if (feed) feed.style.display = "none";
    empty?.classList.remove("show");

    const params = new URLSearchParams({ type, limit: "50" });
    const response = await fetch(`${appConfig.signalsApiUrl}?${params.toString()}`, {
      headers: { Accept: "application/json" }
    });
    if (!response.ok) throw new Error("Signals API failed");

    const payload = await response.json();
    if (payload?.ok) renderSignals(payload);
  } catch {
    showToast("Could not load signals", "The signals API did not respond.");
  } finally {
    loading?.classList.remove("show");
  }
}

function findSignalStore(storeId) {
  const signal = signalsState.signals.find((item) => Number(item.store?.id) === Number(storeId));
  return signal?.store || null;
}

function renderTrendList(elementId, rows = [], metric = "store_count") {
  const element = document.getElementById(elementId);
  if (!element) return;

  const max = Math.max(...rows.map((row) => Number(row[metric] || 0)), 1);
  element.innerHTML = rows.map((row) => {
    const value = Number(row[metric] || 0);
    const width = Math.max(4, Math.round((value / max) * 100));
    const primary = metric === "average_growth" ? `${row.average_growth}%` : formatNumber(row.store_count);
    return `
      <div class="trend-row">
        <div class="trend-row-top">
          <strong>${escapeHtml(row.label)}</strong>
          <span>${escapeHtml(primary)}</span>
        </div>
        <div class="trend-bar"><i style="width:${width}%"></i></div>
        <div class="trend-row-meta">
          <span>${formatNumber(row.store_count)} stores</span>
          <span>${escapeHtml(row.average_revenue)} avg revenue</span>
        </div>
      </div>
    `;
  }).join("");
}

function renderMarket(payload) {
  marketState = payload.market || null;
  if (!marketState) return;

  const summary = marketState.summary || {};
  const metricCards = document.querySelectorAll("#marketMetrics .metric-card");
  const values = [
    formatNumber(summary.total_stores),
    `${summary.average_growth}%`,
    summary.average_revenue || "—",
    summary.total_traffic || "—",
  ];
  metricCards.forEach((card, index) => {
    const value = card.querySelector("strong");
    if (value) value.textContent = values[index] || "—";
  });

  renderTrendList("categoryTrendList", marketState.categories || [], "store_count");
  renderTrendList("growthTrendList", marketState.growth_categories || [], "average_growth");
  renderTrendList("countryTrendList", marketState.countries || [], "store_count");
  renderTrendList("technologyTrendList", marketState.technologies || [], "store_count");
}

async function loadMarketTrends() {
  const loading = document.getElementById("marketLoading");
  try {
    loading?.classList.add("show");
    const response = await fetch(appConfig.marketApiUrl, {
      headers: { Accept: "application/json" }
    });
    if (!response.ok) throw new Error("Market API failed");
    const payload = await response.json();
    if (payload?.ok) renderMarket(payload);
  } catch {
    showToast("Could not load market trends", "The market trends API did not respond.");
  } finally {
    loading?.classList.remove("show");
  }
}

function renderApps(payload) {
  appsState = payload.apps || null;
  if (!appsState) return;

  const summary = appsState.summary || {};
  const metricCards = document.querySelectorAll("#appsMetrics .metric-card");
  const values = [
    formatNumber(summary.detected_apps),
    formatNumber(summary.stores_with_apps),
    formatNumber(summary.unique_apps),
    summary.average_app_cost || "—",
  ];
  metricCards.forEach((card, index) => {
    const value = card.querySelector("strong");
    if (value) value.textContent = values[index] || "—";
  });

  const appsList = document.getElementById("appsList");
  if (appsList) {
    appsList.innerHTML = (appsState.top_apps || []).map((app) => `
      <button class="app-row ${app.name === appsState.selected_technology ? "active" : ""}" data-app-name="${escapeHtml(app.name)}">
        <span class="app-row-icon">${escapeHtml((app.short_code || app.name || "?").slice(0, 2).toUpperCase())}</span>
        <span><strong>${escapeHtml(app.name)}</strong><span>${escapeHtml(app.category)} · ${escapeHtml(app.average_cost)} avg cost</span></span>
        <span class="app-row-metrics"><strong>${formatNumber(app.store_count)}</strong><span>stores</span></span>
      </button>
    `).join("");
  }

  const maxCategory = Math.max(...(appsState.categories || []).map((row) => Number(row.store_count || 0)), 1);
  const appCategoryList = document.getElementById("appCategoryList");
  if (appCategoryList) {
    appCategoryList.innerHTML = (appsState.categories || []).map((row) => {
      const width = Math.max(4, Math.round((Number(row.store_count || 0) / maxCategory) * 100));
      return `
        <div class="trend-row">
          <div class="trend-row-top">
            <strong>${escapeHtml(row.category)}</strong>
            <span>${formatNumber(row.store_count)} stores</span>
          </div>
          <div class="trend-bar"><i style="width:${width}%"></i></div>
          <div class="trend-row-meta">
            <span>${formatNumber(row.unique_apps)} unique apps</span>
            <span>${formatNumber(row.app_count)} detections</span>
          </div>
        </div>
      `;
    }).join("");
  }

  const selectedAppTitle = document.getElementById("selectedAppTitle");
  const selectedAppSubtitle = document.getElementById("selectedAppSubtitle");
  if (selectedAppTitle) selectedAppTitle.textContent = `Stores using ${appsState.selected_technology || "this app"}`;
  if (selectedAppSubtitle) selectedAppSubtitle.textContent = "Top stores by estimated revenue";

  const appStoreGrid = document.getElementById("appStoreGrid");
  if (appStoreGrid) {
    appStoreGrid.innerHTML = (appsState.stores || []).map((store) => `
      <article class="saved-store-card" data-store-id="${store.id}">
        <div class="saved-store-card-top">
          <div class="store-cell">
            <span class="store-logo ${escapeHtml(store.logoClass)}">${escapeHtml(store.logo)}</span>
            <span class="store-meta"><strong>${escapeHtml(store.name)}</strong><span>${escapeHtml(store.domain)}</span></span>
          </div>
          <span class="category-pill">${escapeHtml(store.category)}</span>
        </div>
        <div class="saved-store-metrics">
          <div><span>Revenue</span><strong>${escapeHtml(store.revenueLabel)}</strong></div>
          <div><span>Traffic</span><strong>${escapeHtml(store.trafficLabel)}</strong></div>
          <div><span>Growth</span><strong>${escapeHtml(store.growth)}%</strong></div>
        </div>
        <div class="saved-card-actions">
          <button class="button secondary" data-app-store-action="open" data-store-id="${store.id}">Open</button>
          <button class="button primary" data-app-store-action="save" data-store-id="${store.id}">Save</button>
        </div>
      </article>
    `).join("");
  }
}

async function loadAppsTechnology(technology = "") {
  const loading = document.getElementById("appsLoading");
  try {
    loading?.classList.add("show");
    const params = new URLSearchParams();
    if (technology) params.set("technology", technology);
    const response = await fetch(`${appConfig.appsApiUrl}?${params.toString()}`, {
      headers: { Accept: "application/json" }
    });
    if (!response.ok) throw new Error("Apps API failed");
    const payload = await response.json();
    if (payload?.ok) renderApps(payload);
  } catch {
    showToast("Could not load app data", "The apps and technology API did not respond.");
  } finally {
    loading?.classList.remove("show");
  }
}

function findAppStore(storeId) {
  return appsState?.stores?.find((item) => Number(item.id) === Number(storeId)) || null;
}

function renderProductCard(product, actionName = "product-action") {
  const store = product.store || {};
  return `
    <article class="product-card" data-store-id="${store.id}">
      <div class="product-card-top">
        <div>
          <h3>${escapeHtml(product.name)}</h3>
          <p>${escapeHtml(product.category)} · ${escapeHtml(store.category || "Store")}</p>
        </div>
        <span class="product-price">${escapeHtml(product.price_label || "—")}</span>
      </div>
      <div class="product-store">
        <div class="store-cell">
          <span class="store-logo ${escapeHtml(store.logoClass)}">${escapeHtml(store.logo)}</span>
          <span class="store-meta"><strong>${escapeHtml(store.name)}</strong><span>${escapeHtml(store.domain)}</span></span>
        </div>
        <span class="category-pill">${escapeHtml(store.growth || 0)}% growth</span>
      </div>
      <div class="product-actions">
        <button class="button secondary" data-${actionName}="open" data-store-id="${store.id}">Open store</button>
        <button class="button primary" data-${actionName}="save" data-store-id="${store.id}">Save</button>
      </div>
    </article>
  `;
}

function renderProducts(payload) {
  productsState = payload.products || null;
  if (!productsState) return;

  const summary = productsState.summary || {};
  const metricCards = document.querySelectorAll("#productsMetrics .metric-card");
  const values = [
    formatNumber(summary.detected_products),
    formatNumber(summary.stores_with_products),
    formatNumber(summary.unique_categories),
    summary.average_price || "—",
  ];
  metricCards.forEach((card, index) => {
    const value = card.querySelector("strong");
    if (value) value.textContent = values[index] || "—";
  });

  const categoryList = document.getElementById("productCategoryList");
  if (categoryList) {
    categoryList.innerHTML = (productsState.categories || []).map((category) => `
      <button class="app-row ${category.category === productsState.selected_category ? "active" : ""}" data-product-category="${escapeHtml(category.category)}">
        <span class="app-row-icon">${escapeHtml(category.category.slice(0, 2).toUpperCase())}</span>
        <span><strong>${escapeHtml(category.category)}</strong><span>${escapeHtml(category.average_price)} avg price</span></span>
        <span class="app-row-metrics"><strong>${formatNumber(category.product_count)}</strong><span>products</span></span>
      </button>
    `).join("");
  }

  const topProductList = document.getElementById("topProductList");
  if (topProductList) {
    topProductList.innerHTML = (productsState.top_products || []).map((product) => renderProductCard(product, "top-product-action")).join("");
  }

  const selectedTitle = document.getElementById("selectedProductCategoryTitle");
  const selectedSubtitle = document.getElementById("selectedProductCategorySubtitle");
  if (selectedTitle) selectedTitle.textContent = `${productsState.selected_category || "Selected"} products`;
  if (selectedSubtitle) selectedSubtitle.textContent = "Top products with store context";

  const categoryProductList = document.getElementById("categoryProductList");
  if (categoryProductList) {
    categoryProductList.innerHTML = (productsState.category_products || []).map((product) => renderProductCard(product)).join("");
  }
}

async function loadProducts(category = "") {
  const loading = document.getElementById("productsLoading");
  try {
    loading?.classList.add("show");
    const params = new URLSearchParams();
    if (category) params.set("category", category);
    const response = await fetch(`${appConfig.productsApiUrl}?${params.toString()}`, {
      headers: { Accept: "application/json" }
    });
    if (!response.ok) throw new Error("Products API failed");
    const payload = await response.json();
    if (payload?.ok) renderProducts(payload);
  } catch {
    showToast("Could not load product data", "The products API did not respond.");
  } finally {
    loading?.classList.remove("show");
  }
}

function findProductStore(storeId) {
  const products = [
    ...(productsState?.top_products || []),
    ...(productsState?.category_products || [])
  ];
  const product = products.find((item) => Number(item.store?.id) === Number(storeId));
  return product?.store || null;
}

document.getElementById("selectAll").addEventListener("change", (event) => {
  document.querySelectorAll(".row-check").forEach((checkbox) => checkbox.checked = event.target.checked);
});

const chips = document.getElementById("chips");
chips.addEventListener("click", (event) => {
  const clearButton = event.target.closest("#clearFilters");
  if (clearButton) {
    activeFilters = {};
    Object.keys(filterInputs).forEach((key) => setFilterInputValue(key, ""));
    renderFilterChips();
    if (isDatabaseSource) loadStores(1);
    else renderStores(stores);
    showToast("Filters cleared", "Showing the full Shopify store index.");
    return;
  }

  const chip = event.target.closest(".chip");
  if (chip) {
    const key = chip.dataset.filterChip;
    if (key) {
      delete activeFilters[key];
      setFilterInputValue(key, "");
      renderFilterChips();
      if (isDatabaseSource) loadStores(1);
    }
  }
});
function updateFilterBadge() {
  document.querySelector(".filter-badge").textContent = document.querySelectorAll(".chip").length;
}

const filterDrawer = document.getElementById("filterDrawer");
const drawerBackdrop = document.getElementById("drawerBackdrop");
function setFilterDrawer(open) {
  filterDrawer.classList.toggle("open", open);
  drawerBackdrop.classList.toggle("open", open);
  filterDrawer.setAttribute("aria-hidden", String(!open));
}
document.getElementById("filterButton").addEventListener("click", () => setFilterDrawer(true));
document.getElementById("closeFilters").addEventListener("click", () => setFilterDrawer(false));
drawerBackdrop.addEventListener("click", () => setFilterDrawer(false));
document.getElementById("applyFilters").addEventListener("click", () => {
  activeFilters = collectFilters();
  renderFilterChips();
  if (isDatabaseSource) loadStores(1);
  setFilterDrawer(false);
  showToast("Filters applied", "Your Shopify audience has been refreshed.");
});
document.getElementById("resetDrawer").addEventListener("click", () => {
  activeFilters = {};
  Object.keys(filterInputs).forEach((key) => setFilterInputValue(key, ""));
  renderFilterChips();
  if (isDatabaseSource) loadStores(1);
});
document.querySelectorAll(".option").forEach((option) => option.addEventListener("click", () => option.classList.toggle("active")));
renderFilterChips();

const searchModal = document.getElementById("searchModal");
const globalSearch = document.getElementById("globalSearch");
function setSearchModal(open) {
  searchModal.classList.toggle("open", open);
  if (open) setTimeout(() => globalSearch.focus(), 80);
  else globalSearch.value = "";
}
document.getElementById("searchTrigger").addEventListener("click", () => setSearchModal(true));
searchModal.addEventListener("click", (event) => {
  if (event.target === searchModal) setSearchModal(false);
});
document.addEventListener("keydown", (event) => {
  if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === "k") {
    event.preventDefault();
    setSearchModal(true);
  }
  if (event.key === "Escape") {
    setSearchModal(false);
    setFilterDrawer(false);
    setDetailPanel(false);
  }
});
globalSearch.addEventListener("input", () => {
  const query = globalSearch.value.toLowerCase();
  document.querySelectorAll(".command-result").forEach((result) => {
    result.style.display = result.innerText.toLowerCase().includes(query) ? "flex" : "none";
  });
});
document.querySelectorAll(".command-result").forEach((result) => {
  result.addEventListener("click", () => {
    searchInput.value = result.dataset.search;
    searchInput.dispatchEvent(new Event("input"));
    setSearchModal(false);
    searchInput.scrollIntoView({ behavior: "smooth", block: "center" });
  });
});

const detailPanel = document.getElementById("detailPanel");
const detailBackdrop = document.getElementById("detailBackdrop");

async function fetchStoreDetail(storeId) {
  if (!appConfig.storeApiUrl || !storeId) return null;

  const params = new URLSearchParams({ id: String(storeId) });
  const response = await fetch(`${appConfig.storeApiUrl}?${params.toString()}`, {
    headers: { Accept: "application/json" }
  });
  if (!response.ok) throw new Error("Store detail API failed");
  const payload = await response.json();
  return payload?.ok ? payload : null;
}

function setDetailPanel(open, store, hydrated = false) {
  detailPanel.classList.toggle("open", open);
  detailBackdrop.classList.toggle("open", open);
  detailPanel.setAttribute("aria-hidden", String(!open));
  activeDetailStore = open && store ? store : null;
  if (open && store) {
    const profile = storeProfiles[store.name] || fallbackProfile(store);
    document.getElementById("detailContent").innerHTML = `
      <div class="detail-body">
        <div class="detail-identity">
          <span class="store-logo ${escapeHtml(store.logoClass)}">${escapeHtml(store.logo)}</span>
          <div>
            <div class="detail-title-row"><h2>${escapeHtml(store.name)}</h2><span class="verified-badge">${icons.check} Verified</span></div>
            <p>${escapeHtml(store.domain)} · ${escapeHtml(store.category)}</p>
            <div class="detail-location">● Active store <span>·</span> ${escapeHtml(profile.location)}</div>
          </div>
        </div>
        <div class="detail-actions">
          <button class="button primary" data-detail-action="save">${icons.bookmark} Add to list</button>
          <button class="button secondary" data-detail-action="visit">${icons.external} Visit store</button>
        </div>

        <div class="detail-tabs" role="tablist">
          <button class="detail-tab active" data-detail-tab="overview">Overview</button>
          <button class="detail-tab" data-detail-tab="technology">Technology <span>${profile.apps.length}</span></button>
          <button class="detail-tab" data-detail-tab="products">Products</button>
          <button class="detail-tab" data-detail-tab="signals">Signals <i></i></button>
        </div>

        <div class="detail-tab-panel active" data-detail-panel="overview">
          <div class="detail-section detail-section-first">
            <div class="section-heading"><h3>Commerce performance</h3><span>Estimated</span></div>
            <div class="detail-stat-grid">
              <div class="detail-stat"><span>Monthly revenue</span><strong>${store.revenueLabel}</strong><small>↗ ${store.growth}% vs last month</small></div>
              <div class="detail-stat"><span>Monthly traffic</span><strong>${store.trafficLabel}</strong><small>Top 8% of Shopify</small></div>
              <div class="detail-stat"><span>Monthly orders</span><strong>${profile.orders}</strong><small>${profile.conversion} conversion</small></div>
              <div class="detail-stat"><span>Average price</span><strong>${profile.avgPrice}</strong><small>${store.products} products</small></div>
            </div>
          </div>

          <div class="detail-section">
            <div class="section-heading"><h3>Company profile</h3><span>Firmographics</span></div>
            <dl class="data-list">
              <div><dt>Headquarters</dt><dd>${profile.location}</dd></div>
              <div><dt>Country</dt><dd>${profile.country}</dd></div>
              <div><dt>Employees</dt><dd>${profile.employees}</dd></div>
              <div><dt>Founded</dt><dd>${store.founded}</dd></div>
              <div><dt>Store language</dt><dd>${profile.language}</dd></div>
              <div><dt>Currency</dt><dd>${profile.currency}</dd></div>
            </dl>
          </div>

          <div class="detail-section">
            <div class="section-heading"><h3>Contact information</h3><span>Public data</span></div>
            <div class="contact-card">
              <div><span>Email</span><strong>${profile.email}</strong></div>
              <button class="copy-button" data-copy="${profile.email}">Copy</button>
              <div><span>Phone</span><strong>${profile.phone}</strong></div>
              <button class="copy-button" data-copy="${profile.phone}">Copy</button>
            </div>
          </div>

          <div class="detail-section">
            <div class="section-heading"><h3>Social audience</h3><strong>${profile.social} total</strong></div>
            <div class="social-grid">
              <div><span>Instagram</span><strong>${profile.instagram}</strong></div>
              <div><span>TikTok</span><strong>${profile.tiktok}</strong></div>
              <div><span>Facebook</span><strong>${profile.facebook}</strong></div>
            </div>
          </div>
        </div>

        <div class="detail-tab-panel" data-detail-panel="technology">
          <div class="detail-section detail-section-first">
            <div class="section-heading"><h3>Detected technology</h3><span>Updated today</span></div>
            <div class="technology-list">
              <div class="technology-row platform-row"><span class="app-icon shopify-icon">S</span><div><strong>Shopify Plus</strong><small>Ecommerce platform</small></div><span class="tech-date">Since ${store.founded}</span></div>
              ${profile.apps.map(([name, category, date], index) => `
                <div class="technology-row">
                  <span class="app-icon app-color-${index + 1}">${name.charAt(0)}</span>
                  <div><strong>${name}</strong><small>${category}</small></div>
                  <span class="tech-date">${date}</span>
                </div>`).join("")}
            </div>
          </div>
          <div class="detail-section">
            <h3>Technology spend</h3>
            <div class="spend-card"><span>Estimated app spend</span><strong>$1,240 / month</strong><div><i style="width:72%"></i></div><small>Higher than 72% of similar stores</small></div>
          </div>
        </div>

        <div class="detail-tab-panel" data-detail-panel="products">
          <div class="detail-section detail-section-first">
            <div class="section-heading"><h3>Top products</h3><span>${store.products} detected</span></div>
            <div class="product-list">
              ${profile.products.map(([name, category, price], index) => `
                <div class="product-row">
                  <span class="product-thumb product-${index + 1}">${name.charAt(0)}</span>
                  <div><strong>${name}</strong><small>${category}</small></div>
                  <strong>${price}</strong>
                </div>`).join("")}
            </div>
          </div>
          <div class="detail-section">
            <h3>Catalog insights</h3>
            <dl class="data-list">
              <div><dt>Total products</dt><dd>${store.products}</dd></div>
              <div><dt>Average price</dt><dd>${profile.avgPrice}</dd></div>
              <div><dt>Products added (30d)</dt><dd>34</dd></div>
              <div><dt>Products on sale</dt><dd>12%</dd></div>
            </dl>
          </div>
        </div>

        <div class="detail-tab-panel" data-detail-panel="signals">
          <div class="detail-section detail-section-first">
            <div class="section-heading"><h3>Recent buying signals</h3><span>Last 30 days</span></div>
            <div class="signal-note">ShopSignal score: <strong>High intent</strong>. This store is growing and actively investing in its ecommerce stack.</div>
            <div class="signal-timeline">
              ${profile.signals.map(([date, title, description]) => `
                <div class="timeline-item">
                  <span></span>
                  <div><small>${date}</small><strong>${title}</strong><p>${description}</p></div>
                </div>`).join("")}
            </div>
          </div>
        </div>
      </div>`;

    if (!hydrated && appConfig.storeApiUrl && store.id) {
      fetchStoreDetail(store.id)
        .then((payload) => {
          if (!payload?.store || !payload?.profile) return;
          storeProfiles[payload.store.name] = payload.profile;
          const stillOpen = detailPanel.classList.contains("open") && Number(activeDetailStore?.id) === Number(payload.store.id);
          if (stillOpen) setDetailPanel(true, payload.store, true);
        })
        .catch(() => {
          showToast("Using cached store detail", "The full store detail API did not respond.");
        });
    }
  }
}
table.addEventListener("click", (event) => {
  if (event.target.closest("input")) return;
  const row = event.target.closest("tr");
  if (!row) return;
  const store = stores.find((item) => Number(item.id) === Number(row.dataset.storeId));
  setDetailPanel(true, store);
});
document.getElementById("closeDetail").addEventListener("click", () => setDetailPanel(false));
detailBackdrop.addEventListener("click", () => setDetailPanel(false));
detailPanel.addEventListener("click", async (event) => {
  const tab = event.target.closest("[data-detail-tab]");
  if (tab) {
    const selected = tab.dataset.detailTab;
    detailPanel.querySelectorAll("[data-detail-tab]").forEach((item) => item.classList.toggle("active", item === tab));
    detailPanel.querySelectorAll("[data-detail-panel]").forEach((panel) => panel.classList.toggle("active", panel.dataset.detailPanel === selected));
    return;
  }

  const copyButton = event.target.closest("[data-copy]");
  if (copyButton) {
    try {
      await navigator.clipboard.writeText(copyButton.dataset.copy);
      copyButton.textContent = "Copied";
      setTimeout(() => copyButton.textContent = "Copy", 1400);
    } catch {
      showToast("Copy unavailable", copyButton.dataset.copy);
    }
    return;
  }

  const action = event.target.closest("[data-detail-action]");
  if (action?.dataset.detailAction === "save") saveActiveStoreToList();
  if (action?.dataset.detailAction === "visit") showToast("Store link ready", "External navigation is disabled in this mockup.");
});

let toastTimer;
function showToast(title, message) {
  document.getElementById("toastTitle").textContent = title;
  document.getElementById("toastMessage").textContent = message;
  const toast = document.getElementById("toast");
  toast.classList.add("show");
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => toast.classList.remove("show"), 2700);
}
document.getElementById("saveViewButton").addEventListener("click", async () => {
  const defaultName = searchInput.value.trim()
    || activeFilters.technology
    || activeFilters.category
    || activeFilters.product_category
    || "New segment";
  const name = window.prompt("Name this saved view", defaultName);
  if (!name?.trim()) return;

  try {
    const payload = await postSavedSegmentAction({
      action: "create_segment",
      name: name.trim(),
      search: searchInput.value.trim(),
      sort: document.getElementById("sortSelect").value,
      filters: activeFilters
    });
    if (payload?.ok) {
      renderSavedSegments(payload);
      showToast("View saved", `${name.trim()} is ready to reuse.`);
    }
  } catch {
    showToast("Could not save view", "The saved views API did not respond.");
  }
});
document.getElementById("savedViewList")?.addEventListener("click", async (event) => {
  const deleteButton = event.target.closest("[data-delete-segment-id]");
  if (deleteButton) {
    event.stopPropagation();
    try {
      const payload = await postSavedSegmentAction({
        action: "delete_segment",
        segment_id: Number(deleteButton.dataset.deleteSegmentId)
      });
      if (payload?.ok) {
        renderSavedSegments(payload);
        showToast("Saved view deleted", "The segment list was updated.");
      }
    } catch {
      showToast("Could not delete saved view", "The saved views API did not respond.");
    }
    return;
  }

  const segmentButton = event.target.closest("[data-segment-id]");
  if (!segmentButton) return;
  const segment = savedSegmentsState.find((item) => Number(item.id) === Number(segmentButton.dataset.segmentId));
  if (segment) applySavedSegment(segment);
});
document.getElementById("exportButton").addEventListener("click", () => {
  if (!appConfig.exportApiUrl) {
    showToast("Export unavailable", "The export API is not configured.");
    return;
  }

  const params = explorerQueryParams({ scope: "stores", limit: "5000" });
  window.location.href = `${appConfig.exportApiUrl}?${params.toString()}`;
  showToast("Export started", "Downloading a CSV for the current Explorer view.");
});
document.getElementById("columnsButton").addEventListener("click", () => showToast("Columns are customizable", "Drag, hide, and reorder fields in the full product."));

const explorerView = document.getElementById("explorerView");
const listsView = document.getElementById("listsView");
const signalsView = document.getElementById("signalsView");
const marketView = document.getElementById("marketView");
const appsView = document.getElementById("appsView");
const productsView = document.getElementById("productsView");
const placeholderView = document.getElementById("placeholderView");
const placeholderTitle = document.getElementById("placeholderTitle");
document.querySelectorAll(".nav-item").forEach((item) => {
  item.addEventListener("click", () => {
    document.querySelectorAll(".nav-item").forEach((nav) => nav.classList.remove("active"));
    item.classList.add("active");
    const isExplorer = item.dataset.view === "explorer";
    const isLists = item.dataset.view === "lists";
    const isSignals = item.dataset.view === "signals";
    const isMarket = item.dataset.view === "market";
    const isApps = item.dataset.view === "apps";
    const isProducts = item.dataset.view === "products";
    explorerView.style.display = isExplorer ? "block" : "none";
    if (listsView) listsView.style.display = isLists ? "block" : "none";
    if (signalsView) signalsView.style.display = isSignals ? "block" : "none";
    if (marketView) marketView.style.display = isMarket ? "block" : "none";
    if (appsView) appsView.style.display = isApps ? "block" : "none";
    if (productsView) productsView.style.display = isProducts ? "block" : "none";
    placeholderView.classList.toggle("show", !isExplorer && !isLists && !isSignals && !isMarket && !isApps && !isProducts);
    placeholderTitle.textContent = item.textContent.replace("⌘ K", "").replace(/[0-9]/g, "").trim();
    if (isLists) loadSavedLists(savedListsState.selected_list?.id);
    if (isSignals) loadSignals(signalsState.type);
    if (isMarket) loadMarketTrends();
    if (isApps) loadAppsTechnology(appsState?.selected_technology || "");
    if (isProducts) loadProducts(productsState?.selected_category || "");
    document.getElementById("sidebar").classList.remove("open");
  });
});
document.getElementById("backToExplorer").addEventListener("click", () => document.querySelector('[data-view="explorer"]').click());
document.getElementById("savedBackToExplorer")?.addEventListener("click", () => document.querySelector('[data-view="explorer"]').click());
document.getElementById("menuButton").addEventListener("click", () => document.getElementById("sidebar").classList.toggle("open"));

document.getElementById("refreshSavedLists")?.addEventListener("click", () => loadSavedLists(savedListsState.selected_list?.id));
document.getElementById("exportSavedList")?.addEventListener("click", () => {
  const listId = savedListsState.selected_list?.id;
  if (!appConfig.exportApiUrl || !listId) {
    showToast("Export unavailable", "Select a saved list before exporting.");
    return;
  }

  const params = new URLSearchParams({ scope: "list", list_id: String(listId), limit: "5000" });
  window.location.href = `${appConfig.exportApiUrl}?${params.toString()}`;
  showToast("Export started", "Downloading a CSV for this saved list.");
});
document.getElementById("createListButton")?.addEventListener("click", async () => {
  const name = window.prompt("Name this saved list", "New prospects");
  if (!name?.trim()) return;

  try {
    const payload = await postSavedListAction({ action: "create_list", name: name.trim() });
    if (payload?.ok) {
      renderSavedLists(payload);
      showToast("List created", `${name.trim()} is ready.`);
    }
  } catch {
    showToast("Could not create list", "The saved list API did not respond.");
  }
});

document.getElementById("savedListsNav")?.addEventListener("click", (event) => {
  const listButton = event.target.closest("[data-list-id]");
  if (!listButton) return;
  loadSavedLists(Number(listButton.dataset.listId));
});

document.getElementById("savedStoreGrid")?.addEventListener("click", async (event) => {
  const action = event.target.closest("[data-saved-action]");
  if (!action) return;

  const storeId = Number(action.dataset.storeId);
  const store = savedListsState.stores.find((item) => Number(item.id) === storeId);

  if (action.dataset.savedAction === "open" && store) {
    storeProfiles = { ...storeProfiles, ...savedListsState.profiles };
    setDetailPanel(true, store);
    return;
  }

  if (action.dataset.savedAction === "remove") {
    try {
      const payload = await postSavedListAction({
        action: "remove_store",
        store_id: storeId,
        list_id: savedListsState.selected_list?.id
      });
      if (payload?.ok) {
        renderSavedLists(payload);
        showToast("Store removed", "The saved list was updated.");
      }
    } catch {
      showToast("Could not remove store", "The saved list API did not respond.");
    }
  }
});

document.getElementById("refreshSignalsButton")?.addEventListener("click", () => loadSignals(signalsState.type));
document.getElementById("signalFilterTabs")?.addEventListener("click", (event) => {
  const filter = event.target.closest("[data-signal-type]");
  if (!filter) return;
  loadSignals(filter.dataset.signalType);
});
document.getElementById("signalsFeed")?.addEventListener("click", async (event) => {
  const action = event.target.closest("[data-signal-action]");
  if (!action) return;

  const storeId = Number(action.dataset.storeId);
  const store = findSignalStore(storeId);
  if (!store) return;

  if (action.dataset.signalAction === "open") {
    storeProfiles = { ...storeProfiles, ...signalsState.profiles };
    setDetailPanel(true, store);
    return;
  }

  if (action.dataset.signalAction === "save") {
    activeDetailStore = store;
    await saveActiveStoreToList();
  }
});
document.getElementById("refreshMarketButton")?.addEventListener("click", () => loadMarketTrends());
document.getElementById("refreshAppsButton")?.addEventListener("click", () => loadAppsTechnology(appsState?.selected_technology || ""));
document.getElementById("appsList")?.addEventListener("click", (event) => {
  const appButton = event.target.closest("[data-app-name]");
  if (!appButton) return;
  loadAppsTechnology(appButton.dataset.appName);
});
document.getElementById("appStoreGrid")?.addEventListener("click", async (event) => {
  const action = event.target.closest("[data-app-store-action]");
  if (!action) return;

  const store = findAppStore(Number(action.dataset.storeId));
  if (!store) return;

  if (action.dataset.appStoreAction === "open") {
    storeProfiles = { ...storeProfiles, ...(appsState?.profiles || {}) };
    setDetailPanel(true, store);
    return;
  }

  if (action.dataset.appStoreAction === "save") {
    activeDetailStore = store;
    await saveActiveStoreToList();
  }
});
document.getElementById("refreshProductsButton")?.addEventListener("click", () => loadProducts(productsState?.selected_category || ""));
document.getElementById("productCategoryList")?.addEventListener("click", (event) => {
  const categoryButton = event.target.closest("[data-product-category]");
  if (!categoryButton) return;
  loadProducts(categoryButton.dataset.productCategory);
});
document.getElementById("topProductList")?.addEventListener("click", handleProductAction);
document.getElementById("categoryProductList")?.addEventListener("click", handleProductAction);

async function handleProductAction(event) {
  const action = event.target.closest("[data-product-action], [data-top-product-action]");
  if (!action) return;

  const actionName = action.dataset.productAction || action.dataset.topProductAction;
  const store = findProductStore(Number(action.dataset.storeId));
  if (!store) return;

  if (actionName === "open") {
    storeProfiles = { ...storeProfiles, ...(productsState?.profiles || {}) };
    setDetailPanel(true, store);
    return;
  }

  if (actionName === "save") {
    activeDetailStore = store;
    await saveActiveStoreToList();
  }
}

loadSavedLists();
loadSavedSegments();
loadSignals();
loadMarketTrends();
loadAppsTechnology();
loadProducts();
