<?php
require_once __DIR__ . '/../config/config.php';
require_admin();

if (!admin_can('settings')) {
    set_flash_message('error', 'You do not have permission to access this page.');
    redirect(SITE_URL . '/admin/dashboard.php');
}

$page_title = 'System Settings';

// Handle settings update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash_message('error', 'Invalid form token. Please try again.');
        header('Location: settings.php');
        exit;
    }

    $maintenance_mode = isset($_POST['maintenance_mode']) ? 1 : 0;
    $maintenance_message = trim($_POST['maintenance_message'] ?? '');
    $site_name = trim($_POST['site_name'] ?? '');
    $site_description = trim($_POST['site_description'] ?? '');
    $admin_email = trim($_POST['admin_email'] ?? '');
    $allow_registration = isset($_POST['allow_registration']) ? 1 : 0;
    $whatsapp_number = trim($_POST['whatsapp_number'] ?? '');
    
    try {
        // Ensure settings table exists
        $pdo->exec("CREATE TABLE IF NOT EXISTS site_settings (
            setting_key VARCHAR(100) PRIMARY KEY,
            setting_value TEXT,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        
        // Update settings
        $settingsData = [
            'maintenance_mode' => $maintenance_mode,
            'maintenance_message' => $maintenance_message,
            'site_name' => $site_name,
            'site_description' => $site_description,
            'admin_email' => $admin_email,
            'whatsapp_number' => $whatsapp_number,
            'allow_registration' => $allow_registration,
            'support_email' => trim($_POST['support_email'] ?? ''),
            'contact_phone' => trim($_POST['contact_phone'] ?? ''),
            'business_hours' => trim($_POST['business_hours'] ?? ''),
            'map_embed_url' => trim($_POST['map_embed_url'] ?? ''),
            'meta_keywords' => trim($_POST['meta_keywords'] ?? ''),
            'battle_wager_min' => max(0, (int)($_POST['battle_wager_min'] ?? 10)),
            'battle_wager_max' => max(1, (int)($_POST['battle_wager_max'] ?? 500)),
            'social_facebook' => trim($_POST['social_facebook'] ?? ''),
            'social_twitter' => trim($_POST['social_twitter'] ?? ''),
            'social_instagram' => trim($_POST['social_instagram'] ?? ''),
            'social_youtube' => trim($_POST['social_youtube'] ?? ''),
            'social_tiktok' => trim($_POST['social_tiktok'] ?? ''),
            'social_linkedin' => trim($_POST['social_linkedin'] ?? '')
        ];
        
        foreach ($settingsData as $key => $value) {
            $stmt = $pdo->prepare("INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?) 
                                   ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
            $stmt->execute([$key, $value]);
        }
        
        // Handle logo upload
        if (!empty($_FILES['site_logo']['name']) && $_FILES['site_logo']['error'] === UPLOAD_ERR_OK) {
            $allowed = ['image/png', 'image/jpeg', 'image/gif', 'image/svg+xml', 'image/webp'];
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $_FILES['site_logo']['tmp_name']);
            finfo_close($finfo);
            
            if (in_array($mime, $allowed) && $_FILES['site_logo']['size'] <= 2 * 1024 * 1024) {
                $ext = strtolower(pathinfo($_FILES['site_logo']['name'], PATHINFO_EXTENSION));
                $logoName = 'site_logo_' . time() . '.' . $ext;
                $uploadDir = ROOT_PATH . '/assets/uploads/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
                
                if (move_uploaded_file($_FILES['site_logo']['tmp_name'], $uploadDir . $logoName)) {
                    $logoPath = 'assets/uploads/' . $logoName;
                    $stmt = $pdo->prepare("INSERT INTO site_settings (setting_key, setting_value) VALUES ('site_logo', ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
                    $stmt->execute([$logoPath]);
                }
            } else {
                set_flash_message('error', 'Logo must be a PNG, JPG, GIF, SVG, or WebP image under 2MB.');
                header('Location: settings.php');
                exit;
            }
        }
        
        log_admin_activity($pdo, 'Updated Settings', 'Updated global site settings');
        set_flash_message('success', 'Settings updated successfully');
        header('Location: settings.php');
        exit;
    } catch (Exception $e) {
        set_flash_message('error', 'Error updating settings: ' . $e->getMessage());
    }
}

// Get current settings
$settings = [];
try {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM site_settings");
    while ($row = $stmt->fetch()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
} catch (Exception $e) {
    // Default settings if table doesn't exist
    $settings = [
        'maintenance_mode' => '0',
        'maintenance_message' => 'We are currently undergoing maintenance. Please check back soon.',
        'site_name' => SITE_NAME_DEFAULT,
        'site_description' => SITE_DESCRIPTION_DEFAULT,
        'admin_email' => ADMIN_EMAIL,
        'whatsapp_number' => WHATSAPP_NUMBER,
        'allow_registration' => '1',
        'site_logo' => ''
    ];
    foreach (array_keys(site_social_networks()) as $network) {
        $settings['social_' . $network] = '';
    }
}

include __DIR__ . '/includes/header.php';
?>

<div class="container-fluid">
  <div class="row">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>
    <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
      
      <!-- Page Header -->
      <div class="page-header">
        <div>
          <h1><i class="fas fa-cogs me-2" style="color: var(--admin-primary);"></i> System Settings</h1>
          <p class="text-muted mb-0">Configure platform settings and preferences</p>
        </div>
      </div>

      <?php echo display_flash_message(); ?>

      <!-- Maintenance Alert -->
      <?php if ($settings['maintenance_mode'] == '1'): ?>
        <div class="alert alert-warning alert-dismissible fade show mb-4" role="alert">
          <i class="fas fa-exclamation-triangle me-2"></i>
          <strong>Maintenance Mode is ON</strong> - Users are currently blocked from accessing the site.
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endif; ?>

      <form method="post" enctype="multipart/form-data" id="settingsForm">
      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
      <div class="row">
        <!-- Maintenance Settings -->
        <div class="col-lg-6 mb-4">
          <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-bottom py-3">
              <h5 class="mb-0"><i class="fas fa-tools me-2 text-warning"></i> Maintenance Mode</h5>
            </div>
            <div class="card-body">
              <form method="post">
                <div class="mb-3">
                  <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" name="maintenance_mode" 
                           id="maintenance_mode" <?php echo $settings['maintenance_mode'] == '1' ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="maintenance_mode">
                      <strong>Enable Maintenance Mode</strong>
                      <div class="text-muted small">Blocks user access to the entire site</div>
                    </label>
                  </div>
                </div>
                
                <div class="mb-3">
                  <label for="maintenance_message" class="form-label">Maintenance Message</label>
                  <textarea class="form-control" id="maintenance_message" name="maintenance_message" rows="3"><?php echo htmlspecialchars($settings['maintenance_message'] ?? ''); ?></textarea>
                  <div class="form-text">This message will be shown to users during maintenance</div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Site Settings -->
        <div class="col-lg-6 mb-4">
          <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-bottom py-3">
              <h5 class="mb-0"><i class="fas fa-globe me-2 text-primary"></i> Site Configuration</h5>
            </div>
            <div class="card-body">
              <div class="alert alert-success py-2 small mb-3">
                <i class="fas fa-check-circle me-1"></i>
                These values drive the live site. Site Name appears in the header, footer, browser tabs and emails; the WhatsApp number builds the contact page's chat link.
              </div>
              <div class="mb-3">
                <label for="site_name" class="form-label">Site Name</label>
                <input type="text" class="form-control" id="site_name" name="site_name" 
                       value="<?php echo htmlspecialchars($settings['site_name'] ?? ''); ?>">
              </div>
              
              <div class="mb-3">
                <label for="site_description" class="form-label">Site Description</label>
                <textarea class="form-control" id="site_description" name="site_description" rows="2"><?php echo htmlspecialchars($settings['site_description'] ?? ''); ?></textarea>
              </div>
              
              <div class="mb-3">
                <label for="admin_email" class="form-label">Admin Email</label>
                <input type="email" class="form-control" id="admin_email" name="admin_email" 
                       value="<?php echo htmlspecialchars($settings['admin_email'] ?? ''); ?>">
                <div class="form-text">Notifications will be sent to this email</div>
              </div>
              
              <div class="mb-3">
                <label for="whatsapp_number" class="form-label">WhatsApp Number</label>
                <input type="text" class="form-control" id="whatsapp_number" name="whatsapp_number" 
                       value="<?php echo htmlspecialchars($settings['whatsapp_number'] ?? ''); ?>" 
                       placeholder="+2348001234567">
                <div class="form-text">Stored with or without the leading +; the site strips it, because wa.me needs digits only.</div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Social Media Settings -->
      <div class="row">
        <div class="col-12 mb-4">
          <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-bottom py-3">
              <h5 class="mb-0"><i class="fas fa-share-alt me-2 text-primary"></i> Social Media Links</h5>
            </div>
            <div class="card-body">
              <p class="text-muted small mb-3">Leave a field empty to hide that icon on the site. Every handle below is used by the footer, the contact page and the search-engine profile data at the same time.</p>
              <div class="row">
                <?php foreach (site_social_networks() as $key => $network): ?>
                <div class="col-md-6 mb-3">
                  <label for="social_<?php echo $key; ?>" class="form-label">
                    <i class="fab <?php echo $network['icon']; ?> me-1" style="color:<?php echo $network['color']; ?>;"></i><?php echo $network['label']; ?> URL
                  </label>
                  <input type="url" class="form-control" id="social_<?php echo $key; ?>" name="social_<?php echo $key; ?>"
                         value="<?php echo htmlspecialchars($settings['social_' . $key] ?? ''); ?>"
                         placeholder="https://...">
                </div>
                <?php endforeach; ?>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Contact, SEO and Arena settings -->
      <div class="row">
        <div class="col-lg-6 mb-4">
          <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-bottom py-3">
              <h5 class="mb-0"><i class="fas fa-address-book me-2 text-primary"></i> Contact Details</h5>
            </div>
            <div class="card-body">
              <div class="alert alert-success py-2 small mb-3">
                <i class="fas fa-check-circle me-1"></i>
                These replace the details shown on the public <strong>Contact</strong> page and the <strong>maintenance</strong> page.
              </div>
              <div class="mb-3">
                <label for="support_email" class="form-label">Support Email</label>
                <input type="email" class="form-control" id="support_email" name="support_email"
                       value="<?php echo htmlspecialchars($settings['support_email'] ?? site_contact_email()); ?>">
                <div class="form-text">Shown on the contact page as the address students can write to. Blank uses the Admin Email above.</div>
              </div>
              <div class="mb-3">
                <label for="contact_phone" class="form-label">Phone Number</label>
                <input type="text" class="form-control" id="contact_phone" name="contact_phone"
                       value="<?php echo htmlspecialchars($settings['contact_phone'] ?? ''); ?>"
                       placeholder="+234 800 000 0000">
                <div class="form-text">Used for the "Call Us" link. Blank uses the WhatsApp number above.</div>
              </div>
              <div class="mb-3">
                <label for="business_hours" class="form-label">Business Hours</label>
                <input type="text" class="form-control" id="business_hours" name="business_hours"
                       value="<?php echo htmlspecialchars($settings['business_hours'] ?? 'Mon-Fri from 8am to 5pm'); ?>">
              </div>
              <div class="mb-3">
                <label for="map_embed_url" class="form-label">Google Maps Embed URL</label>
                <input type="url" class="form-control" id="map_embed_url" name="map_embed_url"
                       value="<?php echo htmlspecialchars($settings['map_embed_url'] ?? ''); ?>">
                <div class="form-text">From Google Maps: Share &gt; Embed a map &gt; copy the src value of the iframe. Only Google Maps URLs are accepted; leave blank to keep the current map.</div>
              </div>
            </div>
          </div>
        </div>

        <div class="col-lg-6 mb-4">
          <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-bottom py-3">
              <h5 class="mb-0"><i class="fas fa-search me-2 text-success"></i> Search Engines &amp; Battle Arena</h5>
            </div>
            <div class="card-body">
              <div class="mb-3">
                <label for="meta_keywords" class="form-label">Meta Keywords</label>
                <textarea class="form-control" id="meta_keywords" name="meta_keywords" rows="3"
                          placeholder="Leave blank to keep the built-in list"><?php echo htmlspecialchars($settings['meta_keywords'] ?? ''); ?></textarea>
                <div class="form-text">Comma separated. Sent to Google on every page. The Site Description above is the meta description.</div>
              </div>
              <div class="row">
                <div class="col-6 mb-3">
                  <label for="battle_wager_min" class="form-label">Minimum Battle Wager (XP)</label>
                  <input type="number" class="form-control" id="battle_wager_min" name="battle_wager_min" min="1" step="1"
                         value="<?php echo (int)site_battle_wager_min(); ?>">
                </div>
                <div class="col-6 mb-3">
                  <label for="battle_wager_max" class="form-label">Maximum Battle Wager (XP)</label>
                  <input type="number" class="form-control" id="battle_wager_max" name="battle_wager_max" min="1" step="1"
                         value="<?php echo (int)site_battle_wager_max(); ?>">
                </div>
              </div>
              <div class="form-text">Ranged bets must fall between these two values. A wager of 0 (friendly) is always allowed.</div>
            </div>
          </div>
        </div>
      </div>

      <!-- User Settings -->
      <div class="row">
        <div class="col-lg-6 mb-4">
          <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-bottom py-3">
              <h5 class="mb-0"><i class="fas fa-users me-2 text-success"></i> User Registration</h5>
            </div>
            <div class="card-body">
              <div class="alert alert-success py-2 small mb-3">
                <i class="fas fa-check-circle me-1"></i>
                Turning this off closes the sign-up form immediately: new visitors get "registrations are currently closed" and no account is created. Existing members can still log in.
              </div>
              <div class="mb-3">
                <div class="form-check form-switch">
                  <input class="form-check-input" type="checkbox" name="allow_registration" 
                         id="allow_registration" <?php echo $settings['allow_registration'] == '1' ? 'checked' : ''; ?>>
                  <label class="form-check-label" for="allow_registration">
                    <strong>Allow New User Registration</strong>
                    <div class="text-muted small">Users can create new accounts</div>
                  </label>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- System Info -->
        <div class="col-lg-6 mb-4">
          <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-bottom py-3">
              <h5 class="mb-0"><i class="fas fa-info-circle me-2 text-info"></i> System Information</h5>
            </div>
            <div class="card-body">
              <table class="table table-sm">
                <tr>
                  <td><strong>PHP Version:</strong></td>
                  <td><?php echo PHP_VERSION; ?></td>
                </tr>
                <tr>
                  <td><strong>MySQL Version:</strong></td>
                  <td><?php echo $pdo->query("SELECT VERSION()")->fetchColumn(); ?></td>
                </tr>
                <tr>
                  <td><strong>Server Time:</strong></td>
                  <td><?php echo date('Y-m-d H:i:s'); ?></td>
                </tr>
                <tr>
                  <td><strong>Timezone:</strong></td>
                  <td><?php echo date_default_timezone_get(); ?></td>
                </tr>
                <tr>
                  <td><strong>Memory Limit:</strong></td>
                  <td><?php echo ini_get('memory_limit'); ?></td>
                </tr>
                <tr>
                  <td><strong>Max Upload:</strong></td>
                  <td><?php echo ini_get('upload_max_filesize'); ?></td>
                </tr>
              </table>
            </div>
          </div>
        </div>
      </div>

      <!-- Logo Upload -->
      <div class="row">
        <div class="col-lg-6 mb-4">
          <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-bottom py-3">
              <h5 class="mb-0"><i class="fas fa-image me-2 text-info"></i> Site Logo</h5>
            </div>
            <div class="card-body">
              <?php
              $currentLogo = $settings['site_logo'] ?? '';
              if ($currentLogo): ?>
                <div class="mb-3 text-center">
                  <img src="<?php echo SITE_URL . '/' . htmlspecialchars($currentLogo); ?>" alt="Site Logo" 
                       style="max-height: 100px; max-width: 200px;" class="border rounded p-2 bg-light">
                  <div class="text-muted small mt-1">Current logo</div>
                </div>
              <?php endif; ?>
              <div class="mb-3">
                <label for="site_logo" class="form-label">Upload New Logo</label>
                <input type="file" class="form-control" id="site_logo" name="site_logo" accept="image/*">
                <div class="form-text">PNG, JPG, GIF, SVG or WebP, max 2MB. Used as the site logo in the header and browser tab, and on certificates and emails. Leave unset to keep the graduation-cap icon.</div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Save Button -->
      <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
          <button type="submit" class="btn btn-primary">
            <i class="fas fa-save me-2"></i> Save All Settings
          </button>
          <a href="backup.php" class="btn btn-outline-secondary ms-2">
            <i class="fas fa-database me-2"></i> Backup Database
          </a>
        </div>
      </div>
      </form>

    </main>
  </div>
</div>

<script>
// Maintenance mode confirmation
document.getElementById('maintenance_mode').addEventListener('change', function() {
    if (this.checked) {
        if (!confirm('Enabling maintenance mode will block all user access to the site. Continue?')) {
            this.checked = false;
        }
    }
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>


