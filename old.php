<?php
// -----------------------------------------------------------------------------
// Supabase Configuration
// -----------------------------------------------------------------------------
$supabase_url = ''; // استبدل هذا إذا كان لديك مشروع مختلف
$supabase_anon_key = ''; // مفتاح anon public الخاص بك

// -----------------------------------------------------------------------------
// Function to Fetch Data from Supabase
// -----------------------------------------------------------------------------
function fetch_from_supabase($table_name, $params = []) {
    global $supabase_url, $supabase_anon_key;
    
    $api_url = "{$supabase_url}/rest/v1/{$table_name}";
    
    $query_params = [];
    if (!empty($params['select'])) {
        $query_params[] = 'select=' . urlencode($params['select']);
    }
    if (!empty($params['order'])) {
        $query_params[] = 'order=' . urlencode($params['order']);
    }
    if (!empty($params['limit'])) {
        $query_params[] = 'limit=' . intval($params['limit']);
    }
    // For filtering where is_results_announced is true
    if (isset($params['is_results_announced'])) {
        $query_params[] = 'is_results_announced=eq.' . ($params['is_results_announced'] ? 'true' : 'false');
    }

    if (!empty($query_params)) {
        $api_url .= '?' . implode('&', $query_params);
    }

    $ch = curl_init($api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'apikey: ' . $supabase_anon_key,
        'Authorization: Bearer ' . $supabase_anon_key,
        'Content-Type: application/json'
    ]);
    // Add timeout to prevent long hangs
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5); // 5 seconds connection timeout
    curl_setopt($ch, CURLOPT_TIMEOUT, 10); // 10 seconds total timeout

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if ($http_code == 200) {
        return json_decode($response, true);
    } else {
        error_log("Supabase API Error: HTTP {$http_code} - {$response} - cURL Error: {$curl_error} - URL: {$api_url}");
        return []; 
    }
}

// -----------------------------------------------------------------------------
// Fetch ALL Directorates Data
// -----------------------------------------------------------------------------
$directorates_fetch_params = [
    'select' => 'name_ar,is_results_announced,drive_link,announced_at',
    // We will sort in PHP, so Supabase ordering is mainly for consistency if PHP sort fails
    'order' => 'name_ar.asc' 
];
$all_directorates = fetch_from_supabase('directorates', $directorates_fetch_params);

// -----------------------------------------------------------------------------
// Sort Directorates: Announced first, then by Arabic name
// -----------------------------------------------------------------------------
if (!empty($all_directorates)) {
    usort($all_directorates, function ($a, $b) {
        // Primary sort: is_results_announced (true comes before false)
        if ($a['is_results_announced'] !== $b['is_results_announced']) {
            return $a['is_results_announced'] < $b['is_results_announced'] ? 1 : -1; // true (1) < false (0) is false, so -1 for true first
        }
        // Secondary sort: name_ar (alphabetical)
        return strcmp($a['name_ar'], $b['name_ar']);
    });
}

// -----------------------------------------------------------------------------
// Fetch Latest Announced Results for Notification Bar
// -----------------------------------------------------------------------------
$latest_announced_params = [
    'select' => 'name_ar,drive_link,announced_at', 
    'order' => 'announced_at.desc', 
    'limit' => 5, 
    'is_results_announced' => true
];
$latest_announced_directorates = fetch_from_supabase('directorates', $latest_announced_params);

?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <link rel="icon" type="image/png" sizes="192x192" href="https://edu2iq.net/icon-192x192.png" />
    <link rel="icon" type="image/png" sizes="128x128" href="https://edu2iq.net/icon-128x128.png" />
    <link rel="icon" type="image/svg+xml" href="https://edu2iq.net/favicon.svg" />
    <link rel="shortcut icon" href="https://edu2iq.net/favicon.ico" />
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إعلان نتائج الامتحانات - العراق</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&family=Tajawal:wght@400;500;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #1F6FEB;
            --primary-hover-color: #1A5BBF;
            --background-color: #0D1117;
            --card-inactive-bg: #2C2F33;
            --card-active-bg: var(--primary-color);
            --text-color: #E4E6EB;
            --text-muted-color: #90949C;
            --border-color: #4A4F54;
        }
        body {
            font-family: 'Cairo', 'Tajawal', sans-serif;
            background-color: var(--background-color);
            color: var(--text-color);
            overscroll-behavior-y: contain;
        }
        .main-title {
            animation: fadeInDown 1s ease-out;
        }
        .subtitle {
            animation: fadeInUp 1s ease-out 0.3s;
            animation-fill-mode: backwards; 
        }
        @keyframes fadeInDown {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .card {
            background-color: var(--card-inactive-bg);
            border-radius: 0.75rem; 
            padding: 1.25rem; 
            text-align: center;
            transition: transform 0.3s ease, box-shadow 0.3s ease, opacity 0.3s ease;
            opacity: 0; 
            transform: translateY(20px) scale(0.95);
            cursor: default;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            border: 1px solid transparent;
        }
        .card.visible { 
            opacity: 1;
            transform: translateY(0) scale(1);
        }
        .card.active {
            background-color: var(--card-active-bg);
            color: white;
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }
        .card.active:hover, .card.active:focus-visible {
            transform: translateY(-6px) scale(1.04);
            box-shadow: 0 12px 20px -5px rgba(31, 111, 235, 0.35), 0 4px 6px -4px rgba(31, 111, 235, 0.3);
            border-color: rgba(255,255,255,0.3);
        }
        .card.active::before { 
            content: '';
            position: absolute;
            top: -50%; left: -150%;
            width: 300%; height: 200%;
            background: linear-gradient(to right, transparent 0%, rgba(255,255,255,0.15) 50%, transparent 100%);
            transform: rotate(25deg);
            transition: left 0.8s cubic-bezier(0.25, 1, 0.5, 1);
            pointer-events: none;
            opacity: 0;
        }
        .card.active:hover::before, .card.active:focus-visible::before {
            left: 50%;
            opacity: 1;
        }
        .card.inactive {
            filter: grayscale(60%);
            opacity: 0.65;
        }
        .card.hidden-by-search {
            display: none !important; 
        }
        .status-icon {
            margin-right: 0.5rem; 
            font-weight: bold;
            transition: transform 0.3s ease;
        }
        .card.active:hover .status-icon {
            transform: scale(1.2) rotate(-10deg);
        }
        .directorate-name {
            font-size: 1.1rem; 
            font-weight: 600;
        }
        /* Staggered animation for cards appearing */
        /* This PHP loop for delays will now respect the new sort order */
        <?php if (!empty($all_directorates)) : ?>
            <?php foreach (array_keys($all_directorates) as $index): ?>
            .card:nth-child(<?php echo $index + 1; ?>) {
                transition-delay: <?php echo $index * 0.05; ?>s;
            }
            <?php endforeach; ?>
        <?php endif; ?>
        .ripple {
            position: absolute;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.4);
            transform: scale(0);
            animation: ripple-animation 0.6s cubic-bezier(0.25, 0.46, 0.45, 0.94);
            pointer-events: none;
        }
        @keyframes ripple-animation {
            to { transform: scale(4); opacity: 0; }
        }
        .notification-bar {
            background-color: var(--primary-color);
            color: white;
            padding: 0.75rem 1rem;
            text-align: center;
            font-size: 0.9rem; 
            overflow: hidden;
            white-space: nowrap;
            box-shadow: 0 2px 5px rgba(0,0,0,0.25);
        }
        .notification-bar a {
            color: #E0E0E0; 
            text-decoration: underline;
            text-decoration-thickness: 1px;
            text-underline-offset: 2px;
            font-weight: 500;
        }
        .notification-bar a:hover { color: #FFFFFF; }
        .notification-content {
            display: inline-block;
            animation: marquee 25s linear infinite;
        }
        @keyframes marquee {
            0%   { transform: translateX(100%); }
            100% { transform: translateX(-120%); } 
        }
        .search-container {
            position: relative;
        }
        .search-input {
            background-color: var(--card-inactive-bg);
            color: var(--text-color);
            border: 1px solid var(--border-color);
            padding-right: 2.5rem; 
        }
        .search-input:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 2px rgba(31, 111, 235, 0.3);
        }
        .search-input::placeholder { color: var(--text-muted-color); }
        .search-clear-button {
            position: absolute;
            left: 0.75rem; 
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted-color);
            cursor: pointer;
            display: none; 
        }
        .search-clear-button:hover { color: var(--text-color); }
        .refresh-button {
            position: fixed;
            bottom: 1.25rem; 
            left: 1.25rem;  
            background-color: var(--primary-color);
            color: white;
            padding: 0.75rem; 
            width: 3rem; 
            height: 3rem; 
            border-radius: 50%;
            box-shadow: 0 4px 10px rgba(0,0,0,0.3);
            z-index: 1000;
            transition: all 0.25s ease-in-out;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .refresh-button i { font-size: 1.25rem; } 
        .refresh-button:hover {
            background-color: var(--primary-hover-color);
            transform: scale(1.1) rotate(-15deg);
        }
        .refresh-button:active { transform: scale(0.95) rotate(0deg); }
        #no-results-message {
            display: none; 
            text-align: center;
            font-size: 1.25rem; 
            color: var(--text-muted-color);
            margin-top: 2rem;
            animation: fadeIn 0.5s ease;
        }
        @media (max-width: 640px) { 
            .directorates-grid { grid-template-columns: 1fr; }
            .main-title { font-size: 1.75rem; }
            .subtitle { font-size: 0.95rem; }
            .card { padding: 1rem; }
            .directorate-name { font-size: 1rem; }
        }
    </style>
</head>
<body class="min-h-screen">

    <?php if (!empty($latest_announced_directorates)): ?>
    <div class="notification-bar">
        <div class="notification-content-wrapper">
            <span class="notification-content">
                🆕 آخر النتائج المعلنة:
                <?php foreach ($latest_announced_directorates as $index => $dir): ?>
                    <strong><?php echo htmlspecialchars($dir['name_ar']); ?></strong>
                    <?php if (!empty($dir['drive_link'])): ?>
                        <a href="<?php echo htmlspecialchars($dir['drive_link']); ?>" target="_blank" rel="noopener noreferrer">اضغط هنا</a>
                    <?php endif; ?>
                    <?php if ($index < count($latest_announced_directorates) - 1): echo ' | '; endif; ?>
                <?php endforeach; ?>
                 <?php if (count($latest_announced_directorates) < 3) : ?>
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;🆕 آخر النتائج المعلنة:
                <?php foreach ($latest_announced_directorates as $index => $dir): ?>
                    <strong><?php echo htmlspecialchars($dir['name_ar']); ?></strong>
                    <?php if (!empty($dir['drive_link'])): ?>
                        <a href="<?php echo htmlspecialchars($dir['drive_link']); ?>" target="_blank" rel="noopener noreferrer">اضغط هنا</a>
                    <?php endif; ?>
                    <?php if ($index < count($latest_announced_directorates) - 1): echo ' | '; endif; ?>
                <?php endforeach; ?>
                <?php endif; ?>
            </span>
        </div>
    </div>
    <?php endif; ?>

    <header class="py-8 md:py-10 text-center px-4">
        <h1 class="main-title text-3xl sm:text-4xl md:text-5xl font-bold mb-2">📢 إعلان نتائج الامتحانات - <span class="text-[var(--primary-color)]">صادر من ابن الدورة</span></h1>
        <p class="subtitle text-lg md:text-xl text-gray-400">تابع حالة النتائج لجميع المديريات في العراق، بنقرة واحدة.</p>
    </header>

    <section class="mb-8 px-4 md:px-8 max-w-xl mx-auto">
        <div class="search-container">
            <input type="text" id="searchInput" placeholder="🔍 ابحث عن مديرية بالاسم..."
                   class="search-input w-full px-4 py-3 rounded-md focus:ring-2 focus:ring-[var(--primary-color)] focus:border-[var(--primary-color)] outline-none transition-all shadow-sm">
            <span id="searchClearButton" class="search-clear-button" role="button" aria-label="مسح البحث">
                <i class="fas fa-times"></i>
            </span>
        </div>
    </section>

    <main class="px-4 md:px-8 pb-20">
        <?php if (!empty($all_directorates)): ?>
        <div id="directoratesGrid" class="directorates-grid grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-5 max-w-7xl mx-auto">
            <?php foreach ($all_directorates as $directorate): ?>
                <?php
                    // Ensure boolean values are correctly interpreted from Supabase (which might return strings 'true'/'false')
                    $is_active_raw = $directorate['is_results_announced'] ?? false;
                    $is_active = filter_var($is_active_raw, FILTER_VALIDATE_BOOLEAN);

                    $drive_link = $directorate['drive_link'] ?? '';
                    $card_class = $is_active ? 'active' : 'inactive';
                    $status_icon = $is_active ? '✅' : '⏳';
                    $aria_label = $is_active ? 'النتائج معلنة لـ' . htmlspecialchars($directorate['name_ar']) . '. اضغط لعرض النتائج.' : 'النتائج لم تعلن بعد لـ' . htmlspecialchars($directorate['name_ar']);
                ?>
                <div class="card <?php echo $card_class; ?>" 
                     data-name="<?php echo htmlspecialchars(mb_strtolower($directorate['name_ar'])); ?>"
                     <?php if ($is_active && !empty($drive_link)): ?>
                         onclick="openLinkWithRipple(event, '<?php echo htmlspecialchars($drive_link); ?>')"
                         tabindex="0" 
                         role="button"
                         aria-label="<?php echo $aria_label; ?>"
                     <?php else: ?>
                         aria-label="<?php echo $aria_label; ?>"
                     <?php endif; ?>>
                    <span class="directorate-name">🔴 <?php echo htmlspecialchars($directorate['name_ar']); ?></span>
                    <span class="status-icon"><?php echo $status_icon; ?></span>
                    <?php if ($is_active && empty($drive_link)): ?>
                        <p class="text-xs mt-1 opacity-80">(تم الإعلان، الرابط غير متوفر حالياً)</p>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <p id="no-results-message">لم يتم العثور على مديريات تطابق بحثك.</p>
        <?php else: ?>
            <p class="text-center text-xl text-gray-400 mt-10">لا توجد بيانات مديريات لعرضها حاليًا أو حدث خطأ أثناء جلب البيانات.</p>
        <?php endif; ?>
    </main>

    <button onclick="location.reload()" class="refresh-button" title="تحديث القائمة">
        <i class="fas fa-sync-alt"></i>
    </button>

    <script>
        // This data is now pre-sorted by PHP
        const allDirectoratesData = <?php echo json_encode($all_directorates); ?>;

        function openLinkWithRipple(event, url) {
            const card = event.currentTarget;
            if (!card.classList.contains('active') || !url) return;

            const ripple = document.createElement("span");
            ripple.classList.add("ripple");
            card.appendChild(ripple);

            const rect = card.getBoundingClientRect();
            const size = Math.max(rect.width, rect.height);
            ripple.style.width = ripple.style.height = size + "px";
            ripple.style.left = event.clientX - rect.left - size / 2 + "px";
            ripple.style.top = event.clientY - rect.top - size / 2 + "px";

            setTimeout(() => {
                window.open(url, '_blank', 'noopener,noreferrer');
            }, 100); 

            ripple.addEventListener('animationend', () => { ripple.remove(); });
        }

        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('searchInput');
            const searchClearButton = document.getElementById('searchClearButton');
            const directoratesGrid = document.getElementById('directoratesGrid');
            const noResultsMessage = document.getElementById('no-results-message');
            const cards = directoratesGrid ? Array.from(directoratesGrid.getElementsByClassName('card')) : [];

            // Initial card animation (applies to the sorted list)
            cards.forEach((card, index) => {
                // Ensure the card element exists before trying to add a class
                if (card) { 
                    setTimeout(() => card.classList.add('visible'), 50 * index);
                }
            });
            
            function filterDirectorates() {
                const searchTerm = searchInput.value.toLowerCase().trim();
                let visibleCount = 0;

                cards.forEach(card => {
                    if (!card) return; // Skip if card is null
                    const directorateName = card.dataset.name; 
                    const isMatch = directorateName.includes(searchTerm);
                    
                    if (isMatch) {
                        card.classList.remove('hidden-by-search');
                        visibleCount++;
                    } else {
                        card.classList.add('hidden-by-search');
                    }
                });

                if (noResultsMessage && directoratesGrid) {
                    noResultsMessage.style.display = visibleCount === 0 && searchTerm !== '' ? 'block' : 'none';
                }
                if (searchClearButton) {
                    searchClearButton.style.display = searchTerm ? 'inline' : 'none';
                }
            }

            if (searchInput) {
                searchInput.addEventListener('input', filterDirectorates);
            }

            if (searchClearButton) {
                searchClearButton.addEventListener('click', () => {
                    searchInput.value = '';
                    filterDirectorates();
                    searchInput.focus();
                });
            }

            cards.forEach(card => {
                if (card && card.classList.contains('active') && card.getAttribute('onclick')) {
                    card.addEventListener('keypress', function(event) {
                        if (event.key === 'Enter' || event.key === ' ') {
                            event.preventDefault(); 
                            card.click();
                        }
                    });
                }
            });
        });
    </script>
</body>
</html>
