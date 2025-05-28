<?php
// -----------------------------------------------------------------------------
// Supabase Configuration
// -----------------------------------------------------------------------------
$supabase_url = 'your_SUPABASE_URL_here'; // استبدل هذا إذا كان لديك مشروع مختلف
$supabase_anon_key = 'your_SUPABASE_ANON_KEY_here'; // مفتاح anon public الخاص بك

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
        // Ensure consistent boolean comparison
        $a_announced = filter_var($a['is_results_announced'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $b_announced = filter_var($b['is_results_announced'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if ($a_announced !== $b_announced) {
            return $a_announced < $b_announced ? 1 : -1; // true (1) should come before false (0)
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
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&family=Tajawal:wght@400;500;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #1F6FEB; /* Blue */
            --primary-hover-color: #1A5BBF;
            --secondary-color: #10B981; /* Teal/Green for Telegram button */
            --secondary-hover-color: #0D9488;
            --status-announced-bg: #10B981; /* Green */
            --status-announced-text: #FFFFFF;
            --status-pending-bg: #F59E0B;   /* Yellow */
            --status-pending-text: #1F2937; /* Dark gray for better contrast on yellow */
            
            --background-color: #0A0E14; /* Even darker background */
            --card-bg: #10141A; /* Darker card/table elements */
            --table-header-bg: #1A1F27; /* Slightly lighter than card-bg for header */
            --table-row-odd-bg: #10141A;
            --table-row-even-bg: #13171D; /* Subtle difference for even rows */
            --table-row-hover-bg: #1E232B; /* Darker hover for more contrast */
            
            --text-color: #D1D5DB; /* Light Gray - Tailwind gray-300 */
            --text-muted-color: #6B7280; /* Medium Gray - Tailwind gray-500 */
            --border-color: #2D3748; /* Darker border - Tailwind gray-700 */
        }
        body {
            font-family: 'Cairo', 'Tajawal', sans-serif;
            background-color: var(--background-color);
            color: var(--text-color);
            overscroll-behavior-y: contain;
        }
        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 10px;
            height: 10px;
        }
        ::-webkit-scrollbar-track {
            background: var(--background-color);
            border-radius: 10px;
        }
        ::-webkit-scrollbar-thumb {
            background-color: var(--primary-color);
            border-radius: 10px;
            border: 2px solid var(--background-color);
        }
        ::-webkit-scrollbar-thumb:hover {
            background-color: var(--primary-hover-color);
        }

        .main-title {
            animation: fadeInDown 1s ease-out;
            font-weight: 800; /* Bolder title */
        }
        .subtitle {
            animation: fadeInUp 1s ease-out 0.3s;
            animation-fill-mode: backwards; 
        }
        @keyframes fadeInDown {
            from { opacity: 0; transform: translateY(-25px); } /* Slightly more movement */
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(25px); } /* Slightly more movement */
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: scale(0.95); }
            to { opacity: 1; transform: scale(1); }
        }

        .notification-bar {
            background-color: var(--primary-color);
            color: white;
            padding: 0.75rem 1rem;
            text-align: center;
            font-size: 0.9rem; 
            overflow: hidden;
            white-space: nowrap;
            box-shadow: 0 4px 8px rgba(0,0,0,0.3); /* Enhanced shadow */
        }
        .notification-bar a {
            color: #E0E0E0; 
            text-decoration: underline;
            text-decoration-thickness: 1.5px; /* Slightly thicker underline */
            text-underline-offset: 3px;
            font-weight: 600; /* Bolder link text */
            transition: color 0.2s ease;
        }
        .notification-bar a:hover { color: #FFFFFF; text-shadow: 0 0 5px rgba(255,255,255,0.5); }
        .notification-content {
            display: inline-block;
            animation: marquee 35s linear infinite; 
        }
        @keyframes marquee {
            0%   { transform: translateX(100%); }
            100% { transform: translateX(-120%); } 
        }
        .search-container {
            position: relative;
        }
        .search-input {
            background-color: var(--card-bg);
            color: var(--text-color);
            border: 1px solid var(--border-color);
            padding-right: 3rem; /* Increased padding for clear button */
            transition: all 0.3s ease;
        }
        .search-input:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(31, 111, 235, 0.4), 0 2px 5px rgba(0,0,0,0.2); /* Enhanced focus shadow */
            background-color: #13171D; /* Slightly lighter on focus */
        }
        .search-input::placeholder { color: var(--text-muted-color); transition: opacity 0.3s ease; }
        .search-input:focus::placeholder { opacity: 0.5; }

        .search-clear-button {
            position: absolute;
            left: 0.75rem; 
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted-color);
            cursor: pointer;
            display: none; 
            padding: 0.5rem; /* Make it easier to click */
            transition: color 0.2s ease, transform 0.2s ease;
        }
        .search-clear-button:hover { color: var(--text-color); transform: translateY(-50%) scale(1.1); }
        
        .refresh-button {
            position: fixed;
            bottom: 1.5rem; /* Slightly more padding from bottom */
            left: 1.5rem;  
            background-color: var(--primary-color);
            color: white;
            width: 3.25rem; /* Slightly larger */
            height: 3.25rem; 
            border-radius: 50%;
            box-shadow: 0 6px 12px rgba(31,111,235,0.3), 0 2px 4px rgba(0,0,0,0.2); /* Enhanced shadow */
            z-index: 1000;
            transition: all 0.25s cubic-bezier(0.68, -0.55, 0.27, 1.55); /* Bouncy transition */
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .refresh-button i { font-size: 1.35rem; transition: transform 0.25s ease-in-out; } 
        .refresh-button:hover {
            background-color: var(--primary-hover-color);
            transform: scale(1.15) rotate(-20deg); /* More pronounced hover effect */
            box-shadow: 0 8px 16px rgba(31,111,235,0.4), 0 4px 8px rgba(0,0,0,0.3);
        }
        .refresh-button:hover i { transform: rotate(360deg); }
        .refresh-button:active { transform: scale(0.9) rotate(0deg); background-color: var(--primary-color); }
        
        /* Table specific styles */
        .table-container, #no-results-message {
            transition: opacity 0.4s ease-out, transform 0.4s ease-out, max-height 0.4s ease-out;
            opacity: 1;
            transform: translateY(0);
            max-height: 2000px; /* Sufficiently large */
        }
        .hidden-by-filter {
            opacity: 0 !important;
            transform: translateY(10px) !important;
            max-height: 0 !important;
            pointer-events: none;
            margin-top: 0 !important; /* For no-results-message */
            padding-top: 0 !important;
            padding-bottom: 0 !important;
        }

        #directoratesTable thead th {
            background-color: var(--table-header-bg);
            color: var(--text-color);
            font-weight: 700; /* Bolder header */
            font-size: 0.9rem; /* Slightly larger header text */
            border-bottom: 2px solid var(--primary-color); /* Accent border */
        }
        #directoratesTable tbody tr {
            border-bottom: 1px solid var(--border-color);
            transition: background-color 0.2s ease-in-out, transform 0.2s ease-in-out;
        }
        #directoratesTable tbody tr:nth-child(odd) {
            background-color: var(--table-row-odd-bg);
        }
        #directoratesTable tbody tr:nth-child(even) {
            background-color: var(--table-row-even-bg);
        }
        #directoratesTable tbody tr:hover {
            background-color: var(--table-row-hover-bg);
            transform: scale(1.01); /* Slight scale on row hover */
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
            position:relative; /* To ensure it's above other rows for shadow */
            z-index:10;
        }
        #directoratesTable td, #directoratesTable th {
            padding: 0.85rem 1.1rem; /* Increased padding */
        }
        .status-badge {
            padding: 0.3rem 0.85rem; 
            font-size: 0.8rem; 
            font-weight: 700; 
            border-radius: 9999px; 
            display: inline-flex;
            align-items: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.15);
            transition: all 0.2s ease-in-out;
        }
        .status-badge:hover {
            transform: scale(1.08);
            filter: brightness(1.1);
        }
        .status-badge.announced {
            background-color: var(--status-announced-bg);
            color: var(--status-announced-text);
        }
        .status-badge.pending {
            background-color: var(--status-pending-bg);
            color: var(--status-pending-text);
        }
        .status-badge i {
            margin-right: 0.3rem; 
        }
        html[dir="rtl"] .status-badge i {
            margin-left: 0.3rem;
            margin-right: 0;
        }

        .action-button {
            padding: 0.6rem 1.2rem; 
            font-size: 0.9rem; 
            font-weight: 600; 
            border-radius: 0.5rem; /* More rounded */
            box-shadow: 0 2px 4px rgba(0,0,0,0.1), 0 1px 2px rgba(0,0,0,0.08);
            transition: all 0.2s cubic-bezier(0.25, 0.46, 0.45, 0.94);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            border: 1px solid transparent;
        }
        .action-button:hover {
            transform: translateY(-3px) scale(1.05); /* More pronounced hover */
            box-shadow: 0 6px 12px -2px rgba(0,0,0,0.15), 0 3px 7px -3px rgba(0,0,0,0.1);
        }
        .action-button:active {
            transform: translateY(-1px) scale(0.98);
        }
        .action-button i {
             margin-right: 0.5rem; 
        }
        html[dir="rtl"] .action-button i {
            margin-left: 0.5rem;
            margin-right: 0;
        }
        .action-button-primary {
            background-color: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }
        .action-button-primary:hover {
            background-color: var(--primary-hover-color);
            border-color: var(--primary-hover-color);
        }
        .action-button-secondary {
            background-color: var(--secondary-color);
            color: white;
            border-color: var(--secondary-color);
        }
        .action-button-secondary:hover {
            background-color: var(--secondary-hover-color);
            border-color: var(--secondary-hover-color);
        }
        #no-results-message {
             /* Handled by .hidden-by-filter and .table-container */
            text-align: center;
            font-size: 1.35rem; 
            color: var(--text-muted-color);
            margin-top: 2.5rem; /* More spacing */
            animation: fadeIn 0.5s ease; /* Re-using existing fadeIn */
        }


        @media (max-width: 640px) { 
            .main-title { font-size: 1.6rem; } /* Adjusted for better fit */
            .subtitle { font-size: 0.9rem; }
            #directoratesTable th, #directoratesTable td {
                padding: 0.6rem 0.5rem; 
                font-size: 0.75rem; 
            }
             #directoratesTable th:nth-child(1), #directoratesTable td:nth-child(1) { /* Name column */
                padding-right: 0.8rem; /* More padding for the first column in RTL */
            }
            .action-button {
                padding: 0.5rem 0.9rem;
                font-size: 0.75rem;
            }
            .action-button i {
                margin-right: 0.3rem;
            }
            html[dir="rtl"] .action-button i {
                margin-left: 0.3rem;
                margin-right: 0;
            }
            .status-badge {
                padding: 0.25rem 0.6rem;
                font-size: 0.65rem;
            }
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
                <?php if (count($latest_announced_directorates) < 3) : // Duplicate for continuous scroll effect if few items ?>
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

    <header class="py-8 md:py-12 text-center px-4"> <h1 class="main-title text-3xl sm:text-4xl md:text-5xl font-bold mb-3">📢 إعلان نتائج الامتحانات - <span style="color: var(--primary-color);">صادر من ابن الدورة</span></h1>
        <p class="subtitle text-lg md:text-xl" style="color: var(--text-muted-color);">تابع حالة النتائج لجميع المديريات في العراق، بنقرة واحدة.</p>
    </header>

    <section class="mb-10 px-4 md:px-8 max-w-xl mx-auto"> <div class="search-container">
            <input type="text" id="searchInput" placeholder="🔍 ابحث عن محافظة بالاسم..."
                   class="search-input w-full px-4 py-3.5 rounded-lg focus:ring-2 focus:ring-[var(--primary-color)] focus:border-[var(--primary-color)] outline-none transition-all shadow-md"> <span id="searchClearButton" class="search-clear-button" role="button" aria-label="مسح البحث">
                <i class="fas fa-times fa-lg"></i> </span>
        </div>
    </section>

    <main class="px-4 md:px-8 pb-20">
        <?php if (!empty($all_directorates)): ?>
        <div id="tableContainer" class="table-container overflow-x-auto max-w-5xl mx-auto shadow-2xl rounded-xl" style="background-color: var(--card-bg);"> <table id="directoratesTable" class="w-full text-sm text-right">
                <thead class="text-xs uppercase">
                    <tr>
                        <th scope="col" class="px-6 py-4">اسم المحافظة</th>
                        <th scope="col" class="px-6 py-4 text-center">الحالة</th>
                        <th scope="col" class="px-6 py-4 text-center">الإجراء</th>
                    </tr>
                </thead>
                <tbody id="directoratesTableBody">
                    <?php foreach ($all_directorates as $index => $directorate): ?>
                        <?php
                            $is_active_raw = $directorate['is_results_announced'] ?? false;
                            $is_active = filter_var($is_active_raw, FILTER_VALIDATE_BOOLEAN);
                            $drive_link = $directorate['drive_link'] ?? '';
                            $name_ar = htmlspecialchars($directorate['name_ar']);
                        ?>
                        <tr data-name="<?php echo htmlspecialchars(mb_strtolower($directorate['name_ar'])); ?>">
                            <td class="px-6 py-4 font-semibold whitespace-nowrap" style="color: var(--text-color);"> <?php echo $name_ar; ?>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <?php if ($is_active): ?>
                                    <span class="status-badge announced">
                                        <i class="fas fa-check-circle"></i> تم الإعلان
                                    </span>
                                <?php else: ?>
                                    <span class="status-badge pending">
                                        <i class="fas fa-hourglass-half"></i> لم تعلن بعد
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <?php if ($is_active && !empty($drive_link)): ?>
                                    <a href="<?php echo htmlspecialchars($drive_link); ?>" target="_blank" rel="noopener noreferrer" class="action-button action-button-primary">
                                        <i class="fas fa-download"></i> عرض النتائج
                                    </a>
                                <?php elseif ($is_active && empty($drive_link)): ?>
                                    <span class="text-xs italic" style="color: var(--text-muted-color);">الرابط يضاف قريباً</span>
                                <?php else: // Not active (results not announced) ?>
                                    <a href="https://t.me/edu2iq/10707" target="_blank" rel="noopener noreferrer" class="action-button action-button-secondary">
                                        <i class="fab fa-telegram-plane"></i> متابعة على تلغرام
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p id="no-results-message" class="hidden-by-filter">لم يتم العثور على محافظات تطابق بحثك.</p>
        <?php else: ?>
            <p class="text-center text-xl mt-10" style="color: var(--text-muted-color);">لا توجد بيانات محافظات لعرضها حاليًا أو حدث خطأ أثناء جلب البيانات.</p>
        <?php endif; ?>
    </main>

    <button onclick="location.reload()" class="refresh-button" title="تحديث القائمة">
        <i class="fas fa-sync-alt"></i>
    </button>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('searchInput');
            const searchClearButton = document.getElementById('searchClearButton');
            const directoratesTableBody = document.getElementById('directoratesTableBody');
            const noResultsMessage = document.getElementById('no-results-message');
            const tableContainer = document.getElementById('tableContainer');

            const rows = directoratesTableBody ? Array.from(directoratesTableBody.getElementsByTagName('tr')) : [];

            // Initial row animation
            rows.forEach((row, index) => {
                if (row) {
                    row.style.opacity = '0';
                    row.style.transform = 'translateY(20px) scale(0.95)'; // Enhanced initial transform
                    // Stagger the animation slightly
                    row.style.transition = `opacity 0.5s ease-out ${index * 0.04}s, transform 0.5s ease-out ${index * 0.04}s`;
                    setTimeout(() => {
                        row.style.opacity = '1';
                        row.style.transform = 'translateY(0) scale(1)';
                    }, 20); 
                }
            });
            
            function normalizeArabic(text) {
                if (typeof text !== 'string') return '';
                return text.replace(/[أإآ]/g, 'ا') // Normalize Alef
                           .replace(/ى/g, 'ي')    // Normalize Yaa
                           .replace(/ة/g, 'ه');   // Normalize Taa Marbuta
            }

            function filterDirectorates() {
                const searchTerm = normalizeArabic(searchInput.value.toLowerCase().trim());
                let visibleCount = 0;

                rows.forEach(row => {
                    if (!row) return;
                    
                    const directorateName = normalizeArabic(row.dataset.name ? row.dataset.name.toLowerCase().trim() : '');
                    const isMatch = directorateName.includes(searchTerm);
                    
                    if (isMatch) {
                        row.style.display = ''; 
                        visibleCount++;
                    } else {
                        row.style.display = 'none';
                    }
                });

                const hasSearchTerm = searchTerm !== '';
                if (visibleCount === 0 && hasSearchTerm) {
                    if(noResultsMessage) noResultsMessage.classList.remove('hidden-by-filter');
                    if(tableContainer) tableContainer.classList.add('hidden-by-filter');
                } else {
                    if(noResultsMessage) noResultsMessage.classList.add('hidden-by-filter');
                    if(tableContainer) tableContainer.classList.remove('hidden-by-filter');
                }


                if (searchClearButton) {
                    searchClearButton.style.display = searchTerm ? 'inline-flex' : 'none'; // inline-flex for consistency if icon is flex
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
        });
    </script>
</body>
</html>
