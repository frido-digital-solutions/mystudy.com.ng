<?php
require_once __DIR__ . '/config/config.php';

$page_title = 'Nigerian Universities';
include 'includes/header.php';

// List of Nigerian Universities (Same data as before)
$universities = [
    ['name' => 'University of Lagos', 'abbr' => 'UNILAG', 'state' => 'Lagos', 'type' => 'Federal'],
    ['name' => 'University of Ibadan', 'abbr' => 'UI', 'state' => 'Oyo', 'type' => 'Federal'],
    ['name' => 'Obafemi Awolowo University', 'abbr' => 'OAU', 'state' => 'Osun', 'type' => 'Federal'],
    ['name' => 'University of Nigeria', 'abbr' => 'UNN', 'state' => 'Enugu', 'type' => 'Federal'],
    ['name' => 'Ahmadu Bello University', 'abbr' => 'ABU', 'state' => 'Kaduna', 'type' => 'Federal'],
    ['name' => 'University of Benin', 'abbr' => 'UNIBEN', 'state' => 'Edo', 'type' => 'Federal'],
    ['name' => 'University of Ilorin', 'abbr' => 'UNILORIN', 'state' => 'Kwara', 'type' => 'Federal'],
    ['name' => 'University of Port Harcourt', 'abbr' => 'UNIPORT', 'state' => 'Rivers', 'type' => 'Federal'],
    ['name' => 'Nnamdi Azikiwe University', 'abbr' => 'UNIZIK', 'state' => 'Anambra', 'type' => 'Federal'],
    ['name' => 'University of Jos', 'abbr' => 'UNIJOS', 'state' => 'Plateau', 'type' => 'Federal'],
    ['name' => 'Bayero University Kano', 'abbr' => 'BUK', 'state' => 'Kano', 'type' => 'Federal'],
    ['name' => 'University of Calabar', 'abbr' => 'UNICAL', 'state' => 'Cross River', 'type' => 'Federal'],
    ['name' => 'University of Maiduguri', 'abbr' => 'UNIMAID', 'state' => 'Borno', 'type' => 'Federal'],
    ['name' => 'University of Uyo', 'abbr' => 'UNIUYO', 'state' => 'Akwa Ibom', 'type' => 'Federal'],
    ['name' => 'Federal University of Technology, Akure', 'abbr' => 'FUTA', 'state' => 'Ondo', 'type' => 'Federal'],
    ['name' => 'Federal University of Technology, Minna', 'abbr' => 'FUTMINNA', 'state' => 'Niger', 'type' => 'Federal'],
    ['name' => 'Federal University of Technology, Owerri', 'abbr' => 'FUTO', 'state' => 'Imo', 'type' => 'Federal'],
    ['name' => 'Federal University of Agriculture, Abeokuta', 'abbr' => 'FUNAAB', 'state' => 'Ogun', 'type' => 'Federal'],
    ['name' => 'Federal University of Agriculture, Makurdi', 'abbr' => 'FUAM', 'state' => 'Benue', 'type' => 'Federal'],
    ['name' => 'Lagos State University', 'abbr' => 'LASU', 'state' => 'Lagos', 'type' => 'State'],
    ['name' => 'Osun State University', 'abbr' => 'UNIOSUN', 'state' => 'Osun', 'type' => 'State'],
    ['name' => 'Ekiti State University', 'abbr' => 'EKSU', 'state' => 'Ekiti', 'type' => 'State'],
    ['name' => 'Delta State University', 'abbr' => 'DELSU', 'state' => 'Delta', 'type' => 'State'],
    ['name' => 'Ambrose Alli University', 'abbr' => 'AAU', 'state' => 'Edo', 'type' => 'State'],
    ['name' => 'Rivers State University', 'abbr' => 'RSU', 'state' => 'Rivers', 'type' => 'State'],
    ['name' => 'Covenant University', 'abbr' => 'CU', 'state' => 'Ogun', 'type' => 'Private'],
    ['name' => 'Babcock University', 'abbr' => 'BU', 'state' => 'Ogun', 'type' => 'Private'],
    ['name' => 'Bowen University', 'abbr' => 'BU', 'state' => 'Osun', 'type' => 'Private'],
    ['name' => 'Redeemer\'s University', 'abbr' => 'RUN', 'state' => 'Osun', 'type' => 'Private'],
    ['name' => 'Pan-Atlantic University', 'abbr' => 'PAU', 'state' => 'Lagos', 'type' => 'Private'],
    ['name' => 'Afe Babalola University', 'abbr' => 'ABUAD', 'state' => 'Ekiti', 'type' => 'Private'],
    ['name' => 'Bells University of Technology', 'abbr' => 'BELLS', 'state' => 'Ogun', 'type' => 'Private'],
    ['name' => 'Bingham University', 'abbr' => 'BU', 'state' => 'Nasarawa', 'type' => 'Private'],
    ['name' => 'Caleb University', 'abbr' => 'CU', 'state' => 'Lagos', 'type' => 'Private'],
    ['name' => 'Ajayi Crowther University', 'abbr' => 'ACU', 'state' => 'Oyo', 'type' => 'Private'],
];
?>

<!-- Google Fonts -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<style>
:root {
    --primary-color: #10b981;
    --primary-dark: #059669;
    --secondary-color: #6366f1;
    --bg-warm: #fefdfb;
    --card-bg: #ffffff;
    --text-dark: #1e293b;
    --text-muted: #64748b;
}

body {
    background-color: var(--bg-warm);
    font-family: 'Poppins', sans-serif;
}

.universities-hero {
    background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
    padding: 5rem 0 4rem;
    color: white;
    margin-bottom: 3rem;
    border-radius: 0 0 50px 50px;
    box-shadow: 0 10px 30px rgba(16, 185, 129, 0.2);
}

.universities-hero h1 {
    font-size: 3rem;
    font-weight: 800;
    margin-bottom: 1rem;
}

.universities-hero p {
    font-size: 1.2rem;
    opacity: 0.9;
}

.search-section {
    background: white;
    border-radius: 20px;
    padding: 2rem;
    box-shadow: 0 10px 40px -5px rgba(0, 0, 0, 0.08);
    margin-top: -3rem;
    margin-bottom: 3rem;
    border: 1px solid rgba(0,0,0,0.05);
    position: relative;
    z-index: 10;
}

.form-control {
    border-radius: 12px;
    padding: 0.8rem 1rem;
    border: 2px solid #e2e8f0;
    font-size: 1rem;
}

.form-control:focus {
    border-color: var(--primary-color);
    box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.1);
}

.input-group-text {
    border-radius: 12px 0 0 12px;
    border: 2px solid #e2e8f0;
    border-right: none;
    background: #f8fafc;
}

.filter-tabs {
    display: flex;
    gap: 0.75rem;
    margin-bottom: 2rem;
    flex-wrap: wrap;
    justify-content: center;
}

.filter-btn {
    padding: 0.6rem 1.25rem;
    border: 1px solid #e2e8f0;
    background: white;
    color: var(--text-muted);
    border-radius: 50px;
    font-weight: 600;
    font-size: 0.9rem;
    cursor: pointer;
    transition: all 0.3s ease;
}

.filter-btn:hover {
    background: #f1f5f9;
    color: var(--text-dark);
}

.filter-btn.active {
    background: var(--primary-color);
    color: white;
    border-color: var(--primary-color);
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.25);
}

.university-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 1.5rem;
}

.university-card {
    background: white;
    border-radius: 20px;
    padding: 1.5rem;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    border: 1px solid #f1f5f9;
    height: 100%;
    display: flex;
    flex-direction: column;
}

.university-card:hover {
    transform: translateY(-8px);
    box-shadow: 0 20px 40px -5px rgba(0, 0, 0, 0.1);
    border-color: rgba(16, 185, 129, 0.3);
}

.university-header {
    display: flex;
    align-items: flex-start;
    gap: 1rem;
    margin-bottom: 1rem;
}

.university-icon {
    width: 56px;
    height: 56px;
    background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%);
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
    color: var(--primary-dark);
    font-weight: 800;
    flex-shrink: 0;
}

.university-info h3 {
    font-size: 1.1rem;
    font-weight: 700;
    color: var(--text-dark);
    margin: 0 0 0.25rem 0;
    line-height: 1.3;
}

.university-abbr {
    color: var(--text-muted);
    font-size: 0.85rem;
    font-weight: 600;
}

.university-meta {
    margin-top: auto;
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
    padding-top: 1rem;
    border-top: 1px solid #f1f5f9;
}

.meta-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.35rem 0.85rem;
    border-radius: 50px;
    font-size: 0.75rem;
    font-weight: 600;
}

.type-federal {
    background: #eff6ff;
    color: #2563eb;
}

.type-state {
    background: #f3e8ff;
    color: #9333ea;
}

.type-private {
    background: #fff7ed;
    color: #ea580c;
}

.location-badge {
    background: #f8fafc;
    color: var(--text-muted);
}

.stats-box {
    background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
    color: white;
    padding: 3rem;
    border-radius: 24px;
    text-align: center;
    margin-top: 4rem;
    position: relative;
    overflow: hidden;
    box-shadow: 0 20px 50px -10px rgba(16, 185, 129, 0.3);
}

.stats-box::before {
    content: '';
    position: absolute;
    top: -50px;
    left: -50px;
    width: 200px;
    height: 200px;
    background: rgba(255,255,255,0.1);
    border-radius: 50%;
}

.stats-box h2 {
    font-weight: 800;
    margin-bottom: 2rem;
    position: relative;
}

.stat-row {
    display: flex;
    justify-content: center;
    gap: 3rem;
    flex-wrap: wrap;
    position: relative;
}

.stat-item h3 {
    font-size: 3.5rem;
    font-weight: 800;
    margin-bottom: 0.5rem;
    line-height: 1;
}

.stat-item p {
    font-size: 1rem;
    opacity: 0.9;
    font-weight: 500;
}

@media (max-width: 768px) {
    .universities-hero {
        padding: 3rem 0;
        text-align: center;
        border-radius: 0 0 30px 30px;
    }
    
    .universities-hero h1 {
        font-size: 2rem;
    }
    
    .search-section {
        padding: 1.5rem;
        margin-top: -2rem;
    }
    
    .filter-tabs {
        flex-wrap: nowrap;
        overflow-x: auto;
        justify-content: flex-start;
        padding-bottom: 0.5rem;
    }
    
    .filter-btn {
        white-space: nowrap;
    }
    
    .stat-row {
        gap: 2rem;
    }
}
</style>

<div class="universities-hero">
    <div class="container">
        <h1>Nigerian Universities</h1>
        <p>Explore institutions and access study resources tailored to your school</p>
    </div>
</div>

<div class="container pb-5">
    <!-- Search and Filter Section -->
    <div class="search-section">
        <div class="input-group mb-4">
            <span class="input-group-text">
                <i class="fas fa-search text-muted"></i>
            </span>
            <input type="text" id="universitySearch" class="form-control border-start-0" placeholder="Search universities by name, abbreviation, or state...">
        </div>
        
        <div class="filter-tabs">
            <button class="filter-btn active" onclick="filterUniversities(event, 'all')">
                All Universities
            </button>
            <button class="filter-btn" onclick="filterUniversities(event, 'Federal')">
                Federal
            </button>
            <button class="filter-btn" onclick="filterUniversities(event, 'State')">
                State
            </button>
            <button class="filter-btn" onclick="filterUniversities(event, 'Private')">
                Private
            </button>
        </div>
    </div>

    <!-- Universities Grid -->
    <div class="university-grid" id="universityGrid">
        <?php foreach ($universities as $uni): ?>
        <div class="university-card" data-type="<?php echo $uni['type']; ?>" data-search="<?php echo strtolower($uni['name'] . ' ' . $uni['abbr'] . ' ' . $uni['state']); ?>">
            <div class="university-header">
                <div class="university-icon">
                    <?php echo substr($uni['abbr'], 0, 3); ?>
                </div>
                <div class="university-info">
                    <h3><?php echo htmlspecialchars($uni['name']); ?></h3>
                    <div class="university-abbr"><?php echo htmlspecialchars($uni['abbr']); ?></div>
                </div>
            </div>
            
            <div class="university-meta">
                <span class="meta-badge type-<?php echo strtolower($uni['type']); ?>">
                    <i class="fas fa-university"></i>
                    <?php echo $uni['type']; ?>
                </span>
                <span class="meta-badge location-badge">
                    <i class="fas fa-map-marker-alt"></i>
                    <?php echo $uni['state']; ?>
                </span>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Statistics -->
    <div class="stats-box">
        <h2>Growing Community</h2>
        <div class="stat-row">
            <div class="stat-item">
                <h3><?php echo count(array_filter($universities, fn($u) => $u['type'] === 'Federal')); ?></h3>
                <p>Federal</p>
            </div>
            <div class="stat-item">
                <h3><?php echo count(array_filter($universities, fn($u) => $u['type'] === 'State')); ?></h3>
                <p>State</p>
            </div>
            <div class="stat-item">
                <h3><?php echo count(array_filter($universities, fn($u) => $u['type'] === 'Private')); ?></h3>
                <p>Private</p>
            </div>
        </div>
    </div>
</div>

<script>
// Search functionality
document.getElementById('universitySearch').addEventListener('input', function(e) {
    const searchTerm = e.target.value.toLowerCase();
    const cards = document.querySelectorAll('.university-card');
    
    cards.forEach(card => {
        const searchData = card.getAttribute('data-search');
        if (searchData.includes(searchTerm)) {
            card.style.display = 'flex';
        } else {
            card.style.display = 'none';
        }
    });
});

// Filter functionality
function filterUniversities(event, type) {
    const cards = document.querySelectorAll('.university-card');
    const buttons = document.querySelectorAll('.filter-btn');
    
    // Update active button
    buttons.forEach(btn => btn.classList.remove('active'));
    event.target.classList.add('active');
    
    // Filter cards
    cards.forEach(card => {
        if (type === 'all' || card.getAttribute('data-type') === type) {
            card.style.display = 'flex';
        } else {
            card.style.display = 'none';
        }
    });
}
</script>

<?php include 'includes/footer.php'; ?>
