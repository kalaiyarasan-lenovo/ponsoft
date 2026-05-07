<?php
session_start();
require_once 'includes/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// Get filter values
$code_filter = isset($_GET['code']) ? trim($_GET['code']) : '';
$mobile_filter = isset($_GET['mobile']) ? trim($_GET['mobile']) : '';
$country_filter = isset($_GET['country']) ? trim($_GET['country']) : '';
$city_filter = isset($_GET['city']) ? trim($_GET['city']) : '';
$district_filter = isset($_GET['district']) ? trim($_GET['district']) : '';
$state_filter = isset($_GET['state']) ? trim($_GET['state']) : '';
$year_filter = isset($_GET['year']) ? trim($_GET['year']) : '';
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : '';

// Pagination settings
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

// Build WHERE clause
$where_clauses = [];
$params = [];

if (!empty($code_filter)) {
    $where_clauses[] = "Code = ?";
    $params[] = $code_filter;
}

if (!empty($mobile_filter)) {
    $where_clauses[] = "Mobile_Number LIKE ?";
    $params[] = "%$mobile_filter%";
}

if (!empty($country_filter)) {
    $where_clauses[] = "Country = ?";
    $params[] = $country_filter;
}

if (!empty($city_filter)) {
    $where_clauses[] = "City = ?";
    $params[] = $city_filter;
}

if (!empty($district_filter)) {
    $where_clauses[] = "District = ?";
    $params[] = $district_filter;
}

if (!empty($state_filter)) {
    $where_clauses[] = "State = ?";
    $params[] = $state_filter;
}

if (!empty($year_filter)) {
    if ($status_filter === 'unpaid') {
        $where_clauses[] = "(Year_Paid NOT LIKE ? OR Year_Paid IS NULL)";
        $params[] = "%$year_filter%";
    } else {
        $where_clauses[] = "Year_Paid LIKE ?";
        $params[] = "%$year_filter%";
    }
}

$where_sql = count($where_clauses) > 0 ? "WHERE " . implode(" AND ", $where_clauses) : "";

// Fetch total count and members
try {
    // Count total found
    $count_stmt = $conn->prepare("SELECT COUNT(*) FROM members $where_sql");
    $count_stmt->execute($params);
    $total_found = $count_stmt->fetchColumn();

    // Fetch paginated list
    $stmt = $conn->prepare("SELECT * FROM members $where_sql ORDER BY Code ASC LIMIT ? OFFSET ?");
    foreach ($params as $i => $p) {
        $stmt->bindValue($i + 1, $p);
    }
    $stmt->bindValue(count($params) + 1, $limit, PDO::PARAM_INT);
    $stmt->bindValue(count($params) + 2, $offset, PDO::PARAM_INT);
    $stmt->execute();
    $members = $stmt->fetchAll();
    
    $total_pages = ceil($total_found / $limit);

    // Fetch the next available code
    $next_code_stmt = $conn->query("SELECT MAX(CAST(Code AS UNSIGNED)) FROM members");
    $max_code = $next_code_stmt->fetchColumn();
    $next_code = ($max_code) ? (int)$max_code + 1 : 1;

    // Fetch unique values for dropdown filters (Cascading) with Language detection
    $countries_data = $conn->query("SELECT Country as val, MAX(Language) as Lang FROM members WHERE Country != '' AND Country IS NOT NULL GROUP BY Country ORDER BY Country ASC")->fetchAll(PDO::FETCH_ASSOC);

    $state_sql = "SELECT State as val, MAX(Language) as Lang FROM members WHERE State != '' AND State IS NOT NULL";
    if (!empty($country_filter)) $state_sql .= " AND Country = " . $conn->quote($country_filter);
    $states_data = $conn->query($state_sql . " GROUP BY State ORDER BY State ASC")->fetchAll(PDO::FETCH_ASSOC);

    $dist_sql = "SELECT District as val, MAX(Language) as Lang FROM members WHERE District != '' AND District IS NOT NULL";
    if (!empty($state_filter)) {
        $dist_sql .= " AND State = " . $conn->quote($state_filter);
    } elseif (!empty($country_filter)) {
        $dist_sql .= " AND Country = " . $conn->quote($country_filter);
    }
    $districts_data = $conn->query($dist_sql . " GROUP BY District ORDER BY District ASC")->fetchAll(PDO::FETCH_ASSOC);

    $city_sql = "SELECT City as val, MAX(Language) as Lang FROM members WHERE City != '' AND City IS NOT NULL";
    if (!empty($district_filter)) {
        $city_sql .= " AND District = " . $conn->quote($district_filter);
    } elseif (!empty($state_filter)) {
        $city_sql .= " AND State = " . $conn->quote($state_filter);
    } elseif (!empty($country_filter)) {
        $city_sql .= " AND Country = " . $conn->quote($country_filter);
    }
    $cities_data = $conn->query($city_sql . " GROUP BY City ORDER BY City ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_msg = "Error fetching members: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | Ponsoft Management</title>
    <link rel="stylesheet" href="assets/css/style.css?v=1.3">
</head>
<body class="dashboard-layout">
    <nav class="navbar">
        <div class="nav-brand">
            <div class="nav-logo">P</div>
            <h1>Ponsoft</h1>
        </div>
        <div class="user-nav">

            <div class="user-badge">
                <div class="user-avatar"><?php echo strtoupper(substr($_SESSION['user_name'], 0, 1)); ?></div>
                <div class="user-details">
                    <span class="user-name"><?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
                    <span class="user-role">Administrator</span>
                </div>
            </div>
            <a href="logout.php" class="btn-logout-small">Sign Out</a>
        </div>
    </nav>

    <main class="main-content">
        <div class="content-header-main">
            <div class="directory-title">
                <h3>Members Directory</h3>
                <?php if (!empty($code_filter)): ?>
                    <p class="directory-subtitle">
                        Filtering by Code: "<strong><?php echo htmlspecialchars($code_filter); ?></strong>"
                    </p>
                <?php else: ?>
                    <p class="directory-subtitle">Manage and view all association members</p>
                <?php endif; ?>
            </div>
            <div class="stats-container">
                <div class="stat-badge">
                    <span class="stat-label">Total Records:</span> 
                    <strong class="stat-value"><?php echo number_format($total_found); ?></strong>
                </div>
                <button class="btn-add-member" onclick="openAddModal()">
                    <span class="btn-add-icon">+</span> Add New Member
                </button>
            </div>
        </div>

        <?php if (isset($_SESSION['flash_success'])): ?>
            <div id="flash-success" class="success-message" style="background: #dcfce7; color: #166534; padding: 1rem; border-radius: 0.5rem; margin-bottom: 1.5rem; border: 1px solid #bbf7d0;">
                <?php echo $_SESSION['flash_success']; unset($_SESSION['flash_success']); ?>
            </div>
            <script>
                setTimeout(() => {
                    const msg = document.getElementById('flash-success');
                    if (msg) {
                        msg.style.transition = 'opacity 0.5s ease';
                        msg.style.opacity = '0';
                        setTimeout(() => msg.remove(), 500);
                    }
                }, 2000);
            </script>
        <?php endif; ?>

        <?php if (isset($_SESSION['flash_error'])): ?>
            <div id="flash-error" class="error-message" style="margin-bottom: 1.5rem;">
                <?php echo $_SESSION['flash_error']; unset($_SESSION['flash_error']); ?>
            </div>
            <script>
                setTimeout(() => {
                    const msg = document.getElementById('flash-error');
                    if (msg) {
                        msg.style.transition = 'opacity 0.5s ease';
                        msg.style.opacity = '0';
                        setTimeout(() => msg.remove(), 500);
                    }
                }, 2000);
            </script>
        <?php endif; ?>

        <div class="search-header-container">
            <form action="" method="GET" id="filterForm">
                <div class="main-search-bar">
                    <div class="search-input-group">
                        <span class="search-icon-fixed">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                        </span>
                        <input type="text" name="code" id="codeInput" class="search-input-modern" placeholder="Search by Member Code..." value="<?php echo htmlspecialchars($code_filter); ?>" autocomplete="off" oninput="debouncedSubmit()">
                    </div>
                    <div class="search-input-group">
                        <span class="search-icon-fixed">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path></svg>
                        </span>
                        <input type="text" name="mobile" id="mobileInput" class="search-input-modern" placeholder="Search by Mobile..." maxlength="10" value="<?php echo htmlspecialchars($mobile_filter); ?>" autocomplete="off" oninput="this.value = this.value.replace(/[^0-9]/g, ''); debouncedSubmit()">
                    </div>
                    <button type="button" class="btn-toggle-filters" id="toggleFiltersBtn" onclick="toggleAdvancedFilters()">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="4" y1="21" x2="4" y2="14"></line><line x1="4" y1="10" x2="4" y2="3"></line><line x1="12" y1="21" x2="12" y2="12"></line><line x1="12" y1="8" x2="12" y2="3"></line><line x1="20" y1="21" x2="20" y2="16"></line><line x1="20" y1="12" x2="20" y2="3"></line><line x1="1" y1="14" x2="7" y2="14"></line><line x1="9" y1="12" x2="15" y2="12"></line><line x1="17" y1="16" x2="23" y2="16"></line></svg>
                        Advanced Filters
                    </button>
                    <a href="dashboard.php" class="btn-clear-filters" style="height: 44px; display: flex; align-items: center; gap: 8px;" title="Reset all filters and refresh list">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M23 4v6h-6"></path><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"></path></svg>
                        Reset
                    </a>
                </div>

                <div class="advanced-filters-section" id="advancedFilters">
                    <div class="filters-grid-modern">
                        <div class="filter-control-group">
                            <label>Country</label>
                            <select name="country" id="countryInput" class="modern-select dynamic-font-select" onchange="resetLocationFilters('state'); this.form.submit()">
                                <option value="">All Countries</option>
                                <?php foreach($countries_data as $row): ?>
                                    <?php 
                                        $val = $row['val'];
                                        $hasUnicode = preg_match('/[^\x00-\x7F]/', $val);
                                        $isTamilValue = !$hasUnicode && ($row['Lang'] === 'Tamil');
                                    ?>
                                    <option value="<?php echo htmlspecialchars($val); ?>" <?php echo ($country_filter == $val) ? 'selected' : ''; ?> data-lang="<?php echo $isTamilValue ? 'Tamil' : 'English'; ?>"><?php echo htmlspecialchars($val); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="filter-control-group">
                            <label>State</label>
                            <select name="state" id="stateInput" class="modern-select dynamic-font-select" onchange="resetLocationFilters('district'); this.form.submit()">
                                <option value="">All States</option>
                                <?php foreach($states_data as $row): ?>
                                    <?php 
                                        $val = $row['val'];
                                        $hasUnicode = preg_match('/[^\x00-\x7F]/', $val);
                                        $isTamilValue = !$hasUnicode && ($row['Lang'] === 'Tamil');
                                    ?>
                                    <option value="<?php echo htmlspecialchars($val); ?>" <?php echo ($state_filter == $val) ? 'selected' : ''; ?> data-lang="<?php echo $isTamilValue ? 'Tamil' : 'English'; ?>"><?php echo htmlspecialchars($val); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="filter-control-group">
                            <label>District</label>
                            <select name="district" id="districtInput" class="modern-select dynamic-font-select" onchange="resetLocationFilters('city'); this.form.submit()">
                                <option value="">All Districts</option>
                                <?php foreach($districts_data as $row): ?>
                                    <?php 
                                        $val = $row['val'];
                                        $hasUnicode = preg_match('/[^\x00-\x7F]/', $val);
                                        $isTamilValue = !$hasUnicode && ($row['Lang'] === 'Tamil');
                                    ?>
                                    <option value="<?php echo htmlspecialchars($val); ?>" <?php echo ($district_filter == $val) ? 'selected' : ''; ?> data-lang="<?php echo $isTamilValue ? 'Tamil' : 'English'; ?>"><?php echo htmlspecialchars($val); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="filter-control-group">
                            <label>City or Taluk</label>
                            <select name="city" id="cityInput" class="modern-select dynamic-font-select" onchange="this.form.submit()">
                                <option value="">All Cities</option>
                                <?php foreach($cities_data as $row): ?>
                                    <?php 
                                        $val = $row['val'];
                                        $hasUnicode = preg_match('/[^\x00-\x7F]/', $val);
                                        $isTamilValue = !$hasUnicode && ($row['Lang'] === 'Tamil');
                                    ?>
                                    <option value="<?php echo htmlspecialchars($val); ?>" <?php echo ($city_filter == $val) ? 'selected' : ''; ?> data-lang="<?php echo $isTamilValue ? 'Tamil' : 'English'; ?>"><?php echo htmlspecialchars($val); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="filter-control-group">
                            <label>Payment Year</label>
                            <select name="year" class="modern-select" onchange="this.form.submit()">
                                <option value="">All Years</option>
                                <?php 
                                    $current_y = date("Y");
                                    for($y = $current_y + 1; $y >= 2000; $y--) {
                                        $selected = ($year_filter == $y) ? 'selected' : '';
                                        echo "<option value=\"$y\" $selected>$y</option>";
                                    }
                                ?>
                            </select>
                        </div>
                        <div class="filter-control-group">
                            <label>Status</label>
                            <select name="status" class="modern-select" onchange="this.form.submit()">
                                <option value="paid" <?php echo ($status_filter !== 'unpaid') ? 'selected' : ''; ?>>Paid Members</option>
                                <option value="unpaid" <?php echo ($status_filter === 'unpaid') ? 'selected' : ''; ?>>Unpaid Members</option>
                            </select>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <script>
            function toggleAdvancedFilters() {
                const section = document.getElementById('advancedFilters');
                const btn = document.getElementById('toggleFiltersBtn');
                section.classList.toggle('show');
                btn.classList.toggle('active');
                localStorage.setItem('advancedFiltersVisible', section.classList.contains('show'));
            }

            function resetLocationFilters(type) {
                if (type === 'state') {
                    document.getElementById('stateInput').value = '';
                    document.getElementById('districtInput').value = '';
                    document.getElementById('cityInput').value = '';
                } else if (type === 'district') {
                    document.getElementById('districtInput').value = '';
                    document.getElementById('cityInput').value = '';
                } else if (type === 'city') {
                    document.getElementById('cityInput').value = '';
                }
            }

            // Restore state on load
            document.addEventListener('DOMContentLoaded', () => {
                if (localStorage.getItem('advancedFiltersVisible') === 'true' || 
                    '<?php echo $country_filter . $state_filter . $district_filter . $city_filter . $year_filter; ?>' !== '') {
                    const section = document.getElementById('advancedFilters');
                    const btn = document.getElementById('toggleFiltersBtn');
                    if (section) section.classList.add('show');
                    if (btn) btn.classList.add('active');
                }
            });
        </script>

        <?php if (isset($error_msg)): ?>
            <div class="error-message"><?php echo $error_msg; ?></div>
        <?php endif; ?>

        <div class="table-container">
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Name</th>
                            <th>Mobile</th>
                            <th style="white-space: nowrap;">Last Year Paid</th>
                            <th>Father's Name</th>
                            <th>Address</th>
                            <th>City</th>
                            <th>District</th>
                            <th>State</th>
                            <th>Email</th>
                            <th>Sex</th>
                            <th>Payment 1</th>
                            <th>Payment 2</th>
                            <th>Payment 3</th>
                            <th>Country</th>
                            <th>PinCode</th>
                            <th style="text-align: center;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($members) > 0): ?>
                            <?php foreach ($members as $member): ?>
                                <tr class="clickable-row" onclick="openEditModal('<?php echo $member['Code']; ?>')" <?php echo ($total_found === 1) ? 'style="background: #f0fdf4;"' : ''; ?>>
                                    <td>
                                        <strong><?php echo htmlspecialchars($member['Code'] ?? '-'); ?></strong>
                                    </td>
                                    <?php 
                                        $nameVal = $member['Name'] ?? '';
                                        $hasUnicode = preg_match('/[^\x00-\x7F]/', $nameVal);
                                        $lang = strtolower(trim($member['Language'] ?? ''));
                                        $isTamil = !$hasUnicode && ($lang === 'tamil' || ($member['Country'] ?? '') === ',e;jpah'); 
                                    ?>
                                    <td class="<?php echo $isTamil ? 'sun-tommy-data' : ''; ?>" <?php echo $isTamil ? 'style="font-family: \'SunTommy\', sans-serif !important;"' : ''; ?>>
                                        <?php echo htmlspecialchars($member['Name'] ?? '-'); ?> 
                                        <?php if (($member['VIP'] ?? '') === 'Yes'): ?>
                                            <span class="badge badge-vip" style="font-family: 'Inter', sans-serif !important;" title="VIP Member">V</span>
                                        <?php else: ?>
                                            <span class="badge badge-regular" style="font-family: 'Inter', sans-serif !important;" title="Regular Member">R</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($member['Mobile_Number'] ?? '-'); ?></td>
                                    <td>
                                        <div style="display: flex; flex-wrap: wrap; gap: 4px;">
                                            <?php 
                                            $years = explode(',', $member['Year_Paid'] ?? '');
                                            foreach($years as $year) {
                                                if(trim($year)) {
                                                    echo '<span class="badge" style="background: #e0f2fe; color: #0369a1; font-size: 10px; padding: 2px 6px;">' . htmlspecialchars(trim($year)) . '</span>';
                                                }
                                            }
                                            if(empty(array_filter($years))) echo '<span style="color: var(--text-muted);">None</span>';
                                            ?>
                                        </div>
                                    </td>
                                    <td class="<?php echo $isTamil ? 'sun-tommy-data' : ''; ?>" <?php echo $isTamil ? 'style="font-family: \'SunTommy\', sans-serif !important;"' : ''; ?>><?php echo htmlspecialchars($member['Father_Name'] ?? '-'); ?></td>
                                    <td>
                                        <?php 
                                            $address_parts = [
                                                trim($member['Address_1'] ?? ''), 
                                                trim($member['Address_2'] ?? ''), 
                                                trim($member['Address_3'] ?? ''), 
                                                trim($member['Address_4'] ?? '')
                                            ];
                                            $first = true;
                                            foreach($address_parts as $part) {
                                                if($part !== '') {
                                                    if(!$first) echo ', ';
                                                    echo '<span class="' . ($isTamil ? 'sun-tommy-data' : '') . '" ' . ($isTamil ? 'style="font-family: \'SunTommy\', sans-serif !important;"' : '') . '>' . htmlspecialchars($part) . '</span>';
                                                    $first = false;
                                                }
                                            }
                                            if($first) echo '-';
                                        ?>
                                    </td>
                                    <td class="<?php echo $isTamil ? 'sun-tommy-data' : ''; ?>" <?php echo $isTamil ? 'style="font-family: \'SunTommy\', sans-serif !important;"' : ''; ?>><?php echo htmlspecialchars($member['City'] ?? '-'); ?></td>
                                    <td class="<?php echo $isTamil ? 'sun-tommy-data' : ''; ?>" <?php echo $isTamil ? 'style="font-family: \'SunTommy\', sans-serif !important;"' : ''; ?>><?php echo htmlspecialchars($member['District'] ?? '-'); ?></td>
                                    <td class="<?php echo $isTamil ? 'sun-tommy-data' : ''; ?>" <?php echo $isTamil ? 'style="font-family: \'SunTommy\', sans-serif !important;"' : ''; ?>><?php echo htmlspecialchars($member['State'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars($member['Email'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars($member['Sex'] ?? '-'); ?></td>
                                    <td><?php echo number_format($member['Payment_1'] ?? 0, 2); ?></td>
                                    <td><?php echo number_format($member['Payment_2'] ?? 0, 2); ?></td>
                                    <td><?php echo number_format($member['Payment_3'] ?? 0, 2); ?></td>
                                    <td class="<?php echo $isTamil ? 'sun-tommy-data' : ''; ?>" <?php echo $isTamil ? 'style="font-family: \'SunTommy\', sans-serif !important;"' : ''; ?>><?php echo htmlspecialchars($member['Country'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars($member['PinCode'] ?? '-'); ?></td>
                                    <td>
                                        <div class="action-buttons">
                                            <button class="btn-icon" onclick="event.stopPropagation(); openPaidModal('<?php echo $member['Code']; ?>', <?php echo htmlspecialchars(json_encode($member['Name'] ?? '')); ?>)" style="color: #059669; border-color: #059669;" title="Record Payment">
                                                <span>💰</span>
                                            </button>
                                            <button class="btn-icon" title="View Profile">
                                                <span>✏️</span>
                                            </button>
                                            <a href="includes/member_actions.php?action=delete&code=<?php echo $member['Code']; ?>" 
                                               onclick="event.stopPropagation(); return confirm('Are you sure you want to delete this member?')" 
                                               class="btn-icon delete" title="Delete Member">
                                                <span>🗑️</span>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="17" style="text-align: center; padding: 40px; color: var(--text-muted);">
                                    No members found matching your search.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <div class="pagination-info">
                    Showing <strong><?php echo $offset + 1; ?></strong> to <strong><?php echo min($offset + $limit, $total_found); ?></strong> of <strong><?php echo number_format($total_found); ?></strong> entries
                </div>
                <div class="pagination-controls">
                    <?php 
                        $base_url = "?code=" . urlencode($code_filter) . "&mobile=" . urlencode($mobile_filter) . "&country=" . urlencode($country_filter) . "&city=" . urlencode($city_filter) . "&district=" . urlencode($district_filter) . "&state=" . urlencode($state_filter) . "&year=" . urlencode($year_filter) . "&status=" . urlencode($status_filter) . "&";
                        $range = 2; // Number of pages to show around current page
                    ?>
                    
                    <?php if ($page > 1): ?>
                        <a href="<?php echo $base_url; ?>page=1" class="page-link" title="First Page">&laquo;</a>
                        <a href="<?php echo $base_url; ?>page=<?php echo $page - 1; ?>" class="page-link">Prev</a>
                    <?php endif; ?>

                    <?php
                        for ($i = 1; $i <= $total_pages; $i++) {
                            if ($i == 1 || $i == $total_pages || ($i >= $page - $range && $i <= $page + $range)) {
                                if ($i == $page) {
                                    echo '<span class="current-page">' . $i . '</span>';
                                } else {
                                    echo '<a href="' . $base_url . 'page=' . $i . '" class="page-link">' . $i . '</a>';
                                }
                            } elseif ($i == $page - $range - 1 || $i == $page + $range + 1) {
                                echo '<span class="pagination-ellipsis">...</span>';
                            }
                        }
                    ?>

                    <?php if ($page < $total_pages): ?>
                        <a href="<?php echo $base_url; ?>page=<?php echo $page + 1; ?>" class="page-link">Next</a>
                        <a href="<?php echo $base_url; ?>page=<?php echo $total_pages; ?>" class="page-link" title="Last Page">&raquo;</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </main>

    <!-- Add/Edit Member Modal -->
    <div id="memberModal" class="modal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeModal()">&times;</span>
            <div class="modal-header" style="display: flex; justify-content: space-between; align-items: center; padding-right: 3rem; gap: 20px;">
                <h2 id="modalTitle">Member Profile</h2>
                <div style="display: flex; align-items: center; gap: 15px;">
                    <div class="modal-lang-toggle" style="display: flex; background: #f3f4f6; padding: 4px; border-radius: 8px; border: 1px solid #e5e7eb;">
                        <button type="button" id="modal-lang-english" class="btn-toggle active" onclick="setModalLanguage('English')" style="padding: 6px 12px; border: none; border-radius: 6px; cursor: pointer; font-size: 13px; font-weight: 500; transition: all 0.2s;">English</button>
                        <button type="button" id="modal-lang-tamil" class="btn-toggle" onclick="setModalLanguage('Tamil')" style="padding: 6px 12px; border: none; border-radius: 6px; cursor: pointer; font-size: 13px; font-weight: 500; transition: all 0.2s;">Sun Tommy</button>
                    </div>
                    <button type="button" id="editToggleBtn" class="btn btn-secondary" onclick="enableEditing()">✏️ Edit Details</button>
                </div>
            </div>
            <form id="memberForm" action="includes/member_actions.php?action=add" method="POST" onsubmit="return validateMemberForm()">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="m_Code">Code</label>
                        <input type="text" name="Code" id="m_Code" required>
                    </div>
                    <div class="form-group">
                        <label for="m_Name">Full Name</label>
                        <input type="text" name="Name" id="m_Name" class="sun-tommy-data" required>
                    </div>
                    <div class="form-group">
                        <label for="m_Father_Name">Father's Name</label>
                        <input type="text" name="Father_Name" id="m_Father_Name" class="sun-tommy-data">
                    </div>
                    
                    <div class="form-group">
                        <label for="m_Sex">Sex</label>
                        <select name="Sex" id="m_Sex" class="select-input">
                            <option value="">-- Not Specified --</option>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="m_VIP">VIP Status</label>
                        <select name="VIP" id="m_VIP" class="select-input">
                            <option value="">-- Not Specified --</option>
                            <option value="No">No (Regular)</option>
                            <option value="Yes">Yes (VIP)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="m_Mobile_Number">Mobile Number</label>
                        <input type="text" name="Mobile_Number" id="m_Mobile_Number" maxlength="10" oninput="this.value = this.value.replace(/[^0-9]/g, '')">
                    </div>
                    <div class="form-group">
                        <label for="m_Email">Email</label>
                        <input type="email" name="Email" id="m_Email" placeholder="example@domain.com" maxlength="100">
                    </div>

                    <div class="form-group">
                        <label for="m_Address_1">Address 1</label>
                        <input type="text" name="Address_1" id="m_Address_1" class="sun-tommy-data">
                    </div>
                    <div class="form-group">
                        <label for="m_Address_2">Address 2</label>
                        <input type="text" name="Address_2" id="m_Address_2" class="sun-tommy-data">
                    </div>
                    <div class="form-group">
                        <label for="m_Address_3">Address 3</label>
                        <input type="text" name="Address_3" id="m_Address_3" class="sun-tommy-data">
                    </div>
                    <div class="form-group">
                        <label for="m_Address_4">Address 4</label>
                        <input type="text" name="Address_4" id="m_Address_4" class="sun-tommy-data">
                    </div>
                    
                    <div class="form-group">
                        <label for="m_City">City</label>
                        <input type="text" name="City" id="m_City" class="sun-tommy-data">
                    </div>
                    <div class="form-group">
                        <label for="m_District">District</label>
                        <input type="text" name="District" id="m_District" class="sun-tommy-data">
                    </div>
                    <div class="form-group">
                        <label for="m_State">State</label>
                        <input type="text" name="State" id="m_State" class="sun-tommy-data">
                    </div>

                    <div class="form-group">
                        <label for="m_Country">Country</label>
                        <input type="text" name="Country" id="m_Country" class="sun-tommy-data">
                    </div>
                    <div class="form-group">
                        <label for="m_PinCode">PinCode</label>
                        <input type="text" name="PinCode" id="m_PinCode" maxlength="6" oninput="this.value = this.value.replace(/[^0-9]/g, '')">
                    </div>
                    <div class="form-group" style="display: none;">
                        <label for="m_Language">Language Type</label>
                        <select name="Language" id="m_Language" class="select-input">
                            <option value="English">English (Standard)</option>
                            <option value="Tamil">Tamil (Sun Tommy)</option>
                        </select>
                    </div>
                    <div class="form-group full-width" id="yearSelectionSection">
                        <label>Last Year Paid (Select all that apply)</label>
                        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(80px, 1fr)); gap: 10px; padding: 10px; background: #f9fafb; border: 1px solid var(--border); border-radius: 6px; max-height: 150px; overflow-y: auto;">
                            <?php 
                                $current_year = date("Y");
                                for($y = $current_year + 1; $y >= 2000; $y--) {
                                    echo "<label style='display: flex; align-items: center; gap: 6px; font-size: 0.85rem; cursor: pointer;'>";
                                    echo "<input type='checkbox' name='Year_Paid[]' value='$y' class='year-checkbox'> $y";
                                    echo "</label>";
                                }
                            ?>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="m_Payment_1">Payment 1</label>
                        <input type="number" step="0.01" name="Payment_1" id="m_Payment_1" value="0.00">
                    </div>
                    <div class="form-group">
                        <label for="m_Payment_2">Payment 2</label>
                        <input type="number" step="0.01" name="Payment_2" id="m_Payment_2" value="0.00">
                    </div>
                    <div class="form-group">
                        <label for="m_Payment_3">Payment 3</label>
                        <input type="number" step="0.01" name="Payment_3" id="m_Payment_3" value="0.00">
                    </div>
                </div>

                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="submitBtn">Save Member</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Quick Payment Modal -->
    <div id="quickPaymentModal" class="modal" style="z-index: 1050;">
        <div class="modal-content" style="max-width: 400px; text-align: center;">
            <span class="close-modal" onclick="closeQuickPaymentModal()">&times;</span>
            <h3 style="margin-bottom: 20px;">Record Payment</h3>
            <p style="margin-bottom: 20px; color: var(--text-secondary); line-height: 1.5;">Select the year for member code: <strong id="qp_memberCode"></strong><br><strong id="qp_memberName" class="sun-tommy-data" style="color: var(--text-primary);"></strong></p>
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(80px, 1fr)); gap: 10px; padding: 10px; background: #f9fafb; border: 1px solid var(--border); border-radius: 6px; max-height: 150px; overflow-y: auto; text-align: left;">
                <?php 
                    $current_year = date("Y");
                    for($y = $current_year + 1; $y >= 2000; $y--) {
                        echo "<label style='display: flex; align-items: center; gap: 6px; font-size: 0.85rem; cursor: pointer;'>";
                        echo "<input type='checkbox' name='qp_Year_Paid[]' value='$y' class='qp-year-checkbox'> $y";
                        echo "</label>";
                    }
                ?>
            </div>
            <div style="margin-top: 15px;">
                <button class="btn btn-primary" onclick="submitQuickPayment()">Save Payment</button>
            </div>
        </div>
    </div>

    <script>
        const mobileInput = document.getElementById('mobileInput');
        const codeSearchInput = document.getElementById('codeInput');

        // Force field values from URL params — prevents browser from restoring stale values on F5
        const urlParams = new URLSearchParams(window.location.search);
        if (codeSearchInput) codeSearchInput.value = urlParams.get('code') || '';
        if (mobileInput) mobileInput.value = urlParams.get('mobile') || '';
        if (document.getElementById('countryInput')) document.getElementById('countryInput').value = urlParams.get('country') || '';
        if (document.getElementById('stateInput')) document.getElementById('stateInput').value = urlParams.get('state') || '';

        // Detect browser refresh (F5) vs filter-triggered reload
        const navType = performance.getEntriesByType('navigation')[0]?.type;
        if (navType === 'reload') {
            // Browser refresh — clear saved field so focus defaults to code
            sessionStorage.removeItem('lastFocusedFilter');
        }

        // Track which field the user is actively typing in
        if (codeSearchInput) codeSearchInput.addEventListener('focus', () => sessionStorage.setItem('lastFocusedFilter', 'codeInput'));
        if (mobileInput)     mobileInput.addEventListener('focus',  () => sessionStorage.setItem('lastFocusedFilter', 'mobileInput'));
        if (document.getElementById('cityInput'))     document.getElementById('cityInput').addEventListener('focus',     () => sessionStorage.setItem('lastFocusedFilter', 'cityInput'));
        if (document.getElementById('districtInput')) document.getElementById('districtInput').addEventListener('focus', () => sessionStorage.setItem('lastFocusedFilter', 'districtInput'));
        if (document.getElementById('stateInput'))    document.getElementById('stateInput').addEventListener('focus',    () => sessionStorage.setItem('lastFocusedFilter', 'stateInput'));

        let timeout = null;
        function debouncedSubmit() {
            clearTimeout(timeout);
            timeout = setTimeout(() => {
                document.getElementById('filterForm').submit();
            }, 600);
        }

        // Restore focus to whichever field was last used
        const savedField = sessionStorage.getItem('lastFocusedFilter');
        if (savedField) {
            const field = document.getElementById(savedField);
            if (field) {
                const val = field.value;
                field.value = '';
                field.focus();
                field.value = val;
            }
        } else if (codeSearchInput) {
            const val = codeSearchInput.value;
            codeSearchInput.value = '';
            codeSearchInput.focus();
            codeSearchInput.value = val;
        }

        // Modal Logic
        const modal = document.getElementById('memberModal');
        const form = document.getElementById('memberForm');
        const modalTitle = document.getElementById('modalTitle');
        const codeInput = document.getElementById('m_Code');
        const editToggleBtn = document.getElementById('editToggleBtn');
        const submitBtn = document.getElementById('submitBtn');

        function setFieldsDisabled(disabled) {
            const elements = form.elements;
            for (let i = 0; i < elements.length; i++) {
                const el = elements[i];
                if (el.id === 'm_Code' && form.action.includes('action=edit')) {
                    el.readOnly = true;
                    el.disabled = false;
                } else if (el.tagName === 'INPUT' || el.tagName === 'SELECT' || el.tagName === 'TEXTAREA') {
                    // Force Sex, VIP, and Language to stay enabled as requested
                    if (el.id === 'm_Sex' || el.id === 'm_VIP' || el.id === 'm_Language') {
                        el.disabled = false;
                    } else {
                        el.disabled = disabled;
                    }
                }
            }
            submitBtn.style.display = disabled ? 'none' : 'block';
        }

        function enableEditing() {
            setFieldsDisabled(false);
            document.getElementById('m_Sex').disabled = false;
            document.getElementById('m_VIP').disabled = false;
            document.getElementById('m_Language').disabled = false;
            editToggleBtn.style.display = 'none';
            modalTitle.innerText = 'Update Member: ' + codeInput.value;
        }

        const qpModal = document.getElementById('quickPaymentModal');
        let currentQpCode = null;

        function openPaidModal(code, name) {
            currentQpCode = code;
            document.getElementById('qp_memberCode').innerText = code;
            document.getElementById('qp_memberName').innerText = name ? name : '';
            
            // Clear checkboxes
            document.querySelectorAll('.qp-year-checkbox').forEach(cb => cb.checked = false);

            fetch(`includes/member_actions.php?action=get&code=${code}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const m = data.data;
                        const selectedYears = (m.Year_Paid || '').split(',').map(y => y.trim());
                        document.querySelectorAll('.qp-year-checkbox').forEach(cb => {
                            cb.checked = selectedYears.includes(cb.value);
                        });
                        qpModal.style.display = 'block';
                    } else {
                        alert('Error fetching details');
                    }
                });
        }

        function closeQuickPaymentModal() {
            qpModal.style.display = 'none';
            currentQpCode = null;
        }

        function submitQuickPayment() {
            if (!currentQpCode) return;
            
            const selectedYears = Array.from(document.querySelectorAll('.qp-year-checkbox:checked')).map(cb => cb.value);
            
            const formData = new FormData();
            formData.append('code', currentQpCode);
            // Even if empty, we send it to overwrite with empty
            selectedYears.forEach(year => {
                formData.append('years[]', year);
            });

            fetch('includes/member_actions.php?action=quick_pay_multiple', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if(data.success) {
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
                closeQuickPaymentModal();
            })
            .catch(err => {
                alert('An error occurred');
                closeQuickPaymentModal();
            });
        }

        const nextCode = <?php echo $next_code; ?>;

        function setModalLanguage(lang) {
            const btnEnglish = document.getElementById('modal-lang-english');
            const btnTamil = document.getElementById('modal-lang-tamil');
            const selectLang = document.getElementById('m_Language');
            const inputs = document.querySelectorAll('#memberForm .sun-tommy-data');

            // Update UI
            if (lang === 'Tamil') {
                btnTamil.classList.add('active');
                btnTamil.style.background = '#f97316'; // Orange
                btnTamil.style.color = '#ffffff';
                btnTamil.style.boxShadow = '0 2px 4px rgba(249, 115, 22, 0.2)';
                
                btnEnglish.classList.remove('active');
                btnEnglish.style.background = 'transparent';
                btnEnglish.style.color = '#4b5563';
                btnEnglish.style.boxShadow = 'none';
            } else {
                btnEnglish.classList.add('active');
                btnEnglish.style.background = '#2563eb'; // Blue
                btnEnglish.style.color = '#ffffff';
                btnEnglish.style.boxShadow = '0 2px 4px rgba(37, 99, 235, 0.2)';
                
                btnTamil.classList.remove('active');
                btnTamil.style.background = 'transparent';
                btnTamil.style.color = '#4b5563';
                btnTamil.style.boxShadow = 'none';
            }

            // Update Select
            selectLang.value = lang;

            // Apply fonts to inputs with !important to override the dashboard CSS rules
            inputs.forEach(input => {
                if (lang === 'Tamil') {
                    input.style.setProperty('font-family', "'SunTommy', sans-serif", 'important');
                } else {
                    input.style.setProperty('font-family', "'Inter', sans-serif", 'important');
                }
            });
        }

        function openAddModal() {
            form.reset();
            form.action = 'includes/member_actions.php?action=add';
            modalTitle.innerText = 'Add New Member';
            setFieldsDisabled(false);
            editToggleBtn.style.display = 'none';
            codeInput.value = nextCode;
            codeInput.readOnly = false;
            modal.style.display = 'block';
        }

        function openEditModal(code) {
            modalTitle.innerText = 'Member Profile: ' + code;
            form.action = 'includes/member_actions.php?action=edit';
            
            // Fetch member data
            fetch(`includes/member_actions.php?action=get&code=${code}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const m = data.data;
                        document.getElementById('m_Code').value = m.Code;
                        document.getElementById('m_Name').value = m.Name;
                        document.getElementById('m_Father_Name').value = m.Father_Name;
                        document.getElementById('m_Sex').value = m.Sex || '';
                        document.getElementById('m_VIP').value = m.VIP || '';
                        document.getElementById('m_Mobile_Number').value = m.Mobile_Number;
                        document.getElementById('m_Email').value = m.Email;
                        document.getElementById('m_Address_1').value = m.Address_1;
                        document.getElementById('m_Address_2').value = m.Address_2;
                        document.getElementById('m_Address_3').value = m.Address_3;
                        document.getElementById('m_Address_4').value = m.Address_4;
                        document.getElementById('m_City').value = m.City;
                        document.getElementById('m_District').value = m.District;
                        document.getElementById('m_State').value = m.State;
                        document.getElementById('m_Country').value = m.Country;
                        document.getElementById('m_PinCode').value = m.PinCode;
                        setModalLanguage(m.Language || 'English');
                        
                        // Handle multiple checkboxes
                        const selectedYears = (m.Year_Paid || '').split(',').map(y => y.trim());
                        document.querySelectorAll('.year-checkbox').forEach(cb => {
                            cb.checked = selectedYears.includes(cb.value);
                        });

                        document.getElementById('m_Payment_1').value = m.Payment_1;
                        document.getElementById('m_Payment_2').value = m.Payment_2;
                        document.getElementById('m_Payment_3').value = m.Payment_3;
                        
                        setFieldsDisabled(true);
                        editToggleBtn.style.display = 'block';
                        modal.style.display = 'block';
                    } else {
                        alert('Error fetching member details: ' + data.message);
                    }
                });
        }

        function closeModal() {
            modal.style.display = 'none';
            setModalLanguage('English');
        }

        function validateMemberForm() {
            const name = document.getElementById('m_Name').value.trim();
            const mobile = document.getElementById('m_Mobile_Number').value.trim();
            const years = Array.from(document.querySelectorAll('.year-checkbox:checked'));
            const p1 = parseFloat(document.getElementById('m_Payment_1').value) || 0;
            const p2 = parseFloat(document.getElementById('m_Payment_2').value) || 0;
            const p3 = parseFloat(document.getElementById('m_Payment_3').value) || 0;

            if (name === '' && mobile === '' && years.length === 0 && p1 === 0 && p2 === 0 && p3 === 0) {
                alert('Please provide at least one of the following: Name, Mobile Number, or Payment details (Year/Amount).');
                return false;
            }
            return true;
        }

        window.onclick = function(event) {
            if (event.target == modal) {
                closeModal();
            }
            if (event.target == qpModal) {
                closeQuickPaymentModal();
            }
        }


        // Function to make selects searchable
        function initSearchableSelects() {
            document.querySelectorAll('.dynamic-font-select').forEach(select => {
                // Skip if already initialized
                if (select.nextElementSibling && select.nextElementSibling.classList.contains('searchable-select-wrapper')) return;

                const wrapper = document.createElement('div');
                wrapper.className = 'searchable-select-wrapper';
                
                const input = document.createElement('input');
                input.type = 'text';
                input.className = 'search-dropdown-input';
                input.placeholder = select.options[0].text;
                input.readOnly = true; // Use readOnly by default to act like a select
                
                // Set initial value
                if (select.selectedIndex > 0) {
                    const opt = select.options[select.selectedIndex];
                    const isTamilValue = opt.getAttribute('data-lang') === 'Tamil';
                    input.value = opt.text;
                    if (isTamilValue) {
                        input.classList.add('sun-tommy-data');
                        input.classList.remove('default-value');
                        input.style.fontFamily = "'SunTommy', sans-serif";
                    } else {
                        input.classList.add('default-value');
                        input.classList.remove('sun-tommy-data');
                        input.style.fontFamily = '';
                    }
                } else {
                    input.value = select.options[0].text;
                    input.classList.add('default-value');
                    input.classList.remove('sun-tommy-data');
                }

                const list = document.createElement('div');
                list.className = 'search-dropdown-list';
                
                // Hide original select
                select.style.display = 'none';
                select.parentNode.insertBefore(wrapper, select.nextSibling);
                wrapper.appendChild(input);
                wrapper.appendChild(list);

                function populateList(filter = '') {
                    list.innerHTML = '';
                    let count = 0;
                    const isTamilActive = document.body.classList.contains('sun-tommy-active');
                    
                    Array.from(select.options).forEach((opt, index) => {
                        if (filter === '' || opt.text.toLowerCase().includes(filter.toLowerCase())) {
                            const item = document.createElement('div');
                            item.className = 'search-dropdown-item';
                            const isTamilValue = opt.getAttribute('data-lang') === 'Tamil';
                            
                            if (index === 0) {
                                item.classList.add('default-option');
                            } else if (isTamilValue) {
                                item.classList.add('sun-tommy-data');
                                item.style.fontFamily = "'SunTommy', sans-serif";
                            }
                            
                            item.textContent = opt.text;
                            if (index === select.selectedIndex) item.classList.add('selected');
                            
                            item.onclick = (e) => {
                                e.stopPropagation();
                                select.selectedIndex = index;
                                input.value = opt.text;
                                const isTamilValue = opt.getAttribute('data-lang') === 'Tamil';
                                
                                if (index === 0 || !isTamilValue) {
                                    input.classList.remove('sun-tommy-data');
                                    input.classList.add('default-value');
                                    input.style.fontFamily = '';
                                } else {
                                    input.classList.add('sun-tommy-data');
                                    input.classList.remove('default-value');
                                    input.style.fontFamily = "'SunTommy', sans-serif";
                                }
                                list.classList.remove('show');
                                input.readOnly = true;
                                select.onchange(); // Trigger cascade
                            };
                            list.appendChild(item);
                            count++;
                        }
                    });

                    if (count === 0) {
                        const noRes = document.createElement('div');
                        noRes.className = 'search-dropdown-item no-results';
                        noRes.textContent = 'No results found';
                        list.appendChild(noRes);
                    }
                }

                input.onclick = (e) => {
                    e.stopPropagation();
                    const isShowing = list.classList.contains('show');
                    
                    // Close all other dropdowns
                    document.querySelectorAll('.search-dropdown-list').forEach(l => l.classList.remove('show'));
                    document.querySelectorAll('.search-dropdown-input').forEach(i => i.readOnly = true);
                    document.querySelectorAll('.searchable-select-wrapper').forEach(w => w.style.zIndex = '1');

                    if (!isShowing) {
                        list.classList.add('show');
                        wrapper.style.zIndex = '1001';
                        input.readOnly = false;
                        input.value = ''; // Clear for searching
                        input.focus();
                        populateList();
                    }
                };

                input.oninput = () => {
                    populateList(input.value);
                };

                // Close on click outside
                document.addEventListener('click', () => {
                    if (list.classList.contains('show')) {
                        list.classList.remove('show');
                        wrapper.style.zIndex = '1';
                        input.readOnly = true;
                        input.value = select.selectedIndex > 0 ? select.options[select.selectedIndex].text : '';
                    }
                });
            });
        }

        // Initialize everything on load
        document.addEventListener('DOMContentLoaded', () => {
            initSearchableSelects();
        });
    </script>
</body>
</html>
