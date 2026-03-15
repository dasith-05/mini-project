<?php
session_start();

require_once __DIR__ . '/includes/logger.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/validation.php';

try {
    $db = new PDO('sqlite:' . DB_PATH);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    log_error('Database connection failed', ['error' => $e->getMessage()]);
    header('HTTP/1.1 503 Service Unavailable');
    exit('Service temporarily unavailable.');
}

csrf_ensure_token();

$profile_id = null;
if (!empty($_GET['id'])) {
    $profile_id = (int) $_GET['id'];
} elseif (!empty($_GET['user'])) {
    $stmt = $db->prepare("SELECT id FROM users WHERE student_id = ?");
    $stmt->execute([trim($_GET['user'])]);
    $profile_id = (int) $stmt->fetchColumn();
}

if (!$profile_id) {
    header('Location: index.php');
    exit();
}

$is_own_profile = isset($_SESSION['user_id']) && (int) $_SESSION['user_id'] === $profile_id;

$profile_message = '';
$profile_type = '';

// Handle Profile Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile']) && $is_own_profile) {
    if (!csrf_validate()) {
        $profile_message = 'Invalid request. Please try again.';
        $profile_type = 'error';
    } else {
        $errors = validate_profile_update($_POST, $_FILES);
        if (!empty($errors)) {
            $profile_message = $errors[0];
            $profile_type = 'error';
        } else {
            try {
                $name = trim($_POST['name']);
                $contact = trim($_POST['contact']);
                $remove_pic = !empty($_POST['remove_picture']) && $_POST['remove_picture'] === '1';
                
                $db->beginTransaction();

                // Handle file removal or upload
                $pic_path = null;
                $update_pic_column = false;

                if ($remove_pic) {
                    // Delete old profile picture if exists
                    $stmt = $db->prepare("SELECT profile_picture FROM users WHERE id = ?");
                    $stmt->execute([$profile_id]);
                    $old_pic = $stmt->fetchColumn();
                    if ($old_pic && file_exists(__DIR__ . '/' . $old_pic)) {
                        @unlink(__DIR__ . '/' . $old_pic);
                    }
                    $pic_path = null;
                    $update_pic_column = true;
                } elseif (!empty($_FILES['profile_picture']['name'])) {
                    $upload_dir = __DIR__ . '/uploads/profiles/';
                    if (!is_dir($upload_dir)) {
                        @mkdir($upload_dir, 0755, true);
                    }
                    
                    $ext = pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION);
                    $filename = 'profile_' . $profile_id . '_' . time() . '.' . $ext;
                    $target_file = $upload_dir . $filename;
                    
                    if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $target_file)) {
                        $pic_path = 'uploads/profiles/' . $filename;
                        
                        // Delete old profile picture if exists
                        $stmt = $db->prepare("SELECT profile_picture FROM users WHERE id = ?");
                        $stmt->execute([$profile_id]);
                        $old_pic = $stmt->fetchColumn();
                        if ($old_pic && file_exists(__DIR__ . '/' . $old_pic)) {
                            @unlink(__DIR__ . '/' . $old_pic);
                        }
                        $update_pic_column = true;
                    }
                }

                if ($update_pic_column) {
                    $stmt = $db->prepare("UPDATE users SET name = ?, contact = ?, profile_picture = ? WHERE id = ?");
                    $stmt->execute([$name, $contact, $pic_path, $profile_id]);
                } else {
                    $stmt = $db->prepare("UPDATE users SET name = ?, contact = ? WHERE id = ?");
                    $stmt->execute([$name, $contact, $profile_id]);
                }
                
                $db->commit();
                
                // Update session
                $_SESSION['user_name'] = $name;
                
                $profile_message = 'Profile updated successfully!';
                $profile_type = 'success';
            } catch (Exception $e) {
                $db->rollBack();
                log_error('Profile update failed', ['error' => $e->getMessage()]);
                $profile_message = 'Failed to update profile.';
                $profile_type = 'error';
            }
        }
    }
}

$stmt = $db->prepare("SELECT id, name, student_id, contact, profile_picture FROM users WHERE id = ?");
$stmt->execute([$profile_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    header('Location: index.php');
    exit();
}

// Items this user reported (active only for public; own profile can show all)
$stmt = $db->prepare("SELECT id, title, description, item_type, floor, status FROM items WHERE user_id = ? ORDER BY id DESC");
$stmt->execute([$profile_id]);
$reported_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
$reported_active = array_filter($reported_items, function ($i) { return $i['status'] === 'active'; });
$reported_resolved = array_filter($reported_items, function ($i) { return $i['status'] === 'resolved'; });

// Items this user resolved (returned to owners)
$stmt = $db->prepare("
    SELECT i.id, i.title, i.item_type, i.floor, u.name AS owner_name
    FROM items i
    JOIN users u ON i.user_id = u.id
    WHERE i.resolved_by_user_id = ? AND i.status = 'resolved'
    ORDER BY i.id DESC
");
$stmt->execute([$profile_id]);
$resolved_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

$resolve_count = count($resolved_items);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($user['name']); ?> • TraceIt</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Space+Grotesk:wght@500;600&display=swap');
        body { font-family: 'Inter', sans-serif; }
        .logo-font { font-family: 'Space Grotesk', sans-serif; }
        body { background-color: #0f0f10; background-image: linear-gradient(rgba(14, 165, 233, 0.08) 1px, transparent 1px), linear-gradient(90deg, rgba(14, 165, 233, 0.08) 1px, transparent 1px); background-size: 40px 40px; }
        .glass-panel { background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(20px); border: 1px solid rgba(255, 255, 255, 0.1); }

        /* Pointer Glow Effect */
        .pointer-glow {
            position: fixed;
            width: 400px; height: 400px;
            background: radial-gradient(circle, rgba(14, 165, 233, 0.15) 0%, transparent 70%);
            border-radius: 50%; pointer-events: none;
            transform: translate(-50%, -50%) translateZ(-1px);
            z-index: 1;
            transition: opacity 0.3s ease;
        }

        /* Page Transition */
        .page-fade-in { animation: fadeIn 0.5s ease-out forwards; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }

        /* Page Fade Out */
        .page-fade-out { animation: fadeOut 0.3s ease-in forwards !important; }
        @keyframes fadeOut { from { opacity: 1; } to { opacity: 0; } }
    </style>
</head>
<body class="min-h-screen text-white page-fade-in">
    <div class="pointer-glow"></div>
    <div class="max-w-4xl mx-auto px-6 py-10">
        <div class="flex justify-between items-center mb-8 flex-wrap gap-4">
            <div class="flex items-center gap-4">
                <a href="<?php echo $is_own_profile ? 'index.php' : 'top-tracers.php'; ?>" class="text-zinc-400 hover:text-white transition-colors">
                    <i class="fa-solid fa-arrow-left text-xl"></i>
                </a>
                <h1 class="logo-font text-3xl font-bold tracking-tighter">Profile</h1>
            </div>
            <?php if (!empty($_SESSION['user_id'])): ?>
            <div class="flex items-center gap-3">
                <?php if (!$is_own_profile): ?>
                    <a href="profile.php?id=<?php echo (int) $_SESSION['user_id']; ?>" class="bg-zinc-900/50 border border-zinc-800 text-sky-400 hover:text-sky-300 hover:shadow-[0_0_15px_rgba(14,165,233,0.4)] hover:border-sky-500/50 px-4 py-2.5 rounded-xl transition-all text-sm font-bold flex items-center gap-2">
                        <i class="fa-solid fa-circle-user"></i> My Profile
                    </a>
                <?php endif; ?>
                <a href="index.php" class="bg-zinc-900/50 border border-zinc-800 text-emerald-500 hover:text-emerald-400 hover:shadow-[0_0_15px_rgba(16,185,129,0.4)] hover:border-emerald-500/50 px-4 py-2.5 rounded-xl transition-all text-sm font-bold flex items-center gap-2">
                    <i class="fa-solid fa-map"></i> Map
                </a>
                <a href="top-tracers.php" class="bg-zinc-900/50 border border-zinc-800 text-sky-400 hover:text-sky-300 hover:shadow-[0_0_15px_rgba(14,165,233,0.4)] hover:border-sky-500/50 px-4 py-2.5 rounded-xl transition-all text-sm font-bold flex items-center gap-2">
                    <i class="fa-solid fa-trophy text-amber-400"></i> Top Tracers
                </a>
                <a href="index.php?logout=1" class="bg-zinc-900/50 border border-zinc-800 text-red-500 hover:text-red-400 hover:shadow-[0_0_15px_rgba(239,68,68,0.4)] hover:border-red-500/50 px-4 py-2.5 rounded-xl transition-all text-sm font-bold flex items-center gap-2">
                    <i class="fa-solid fa-power-off"></i> Logout
                </a>
            </div>
            <?php endif; ?>
        </div>
        

        <div class="glass-panel rounded-2xl border border-white/10 p-8 mb-8">
            <?php if ($profile_message): ?>
                <div class="mb-6 p-4 rounded-xl <?php echo $profile_type === 'success' ? 'bg-emerald-500/10 border border-emerald-500/20 text-emerald-400' : 'bg-red-500/10 border border-red-500/20 text-red-400'; ?> text-sm font-medium">
                    <?php echo htmlspecialchars($profile_message); ?>
                </div>
            <?php endif; ?>

            <div class="flex flex-col sm:flex-row items-start sm:items-center gap-6">
                <div class="w-24 h-24 rounded-2xl bg-sky-500/20 border border-sky-500/30 flex items-center justify-center shrink-0 overflow-hidden">
                    <?php if (!empty($user['profile_picture']) && file_exists(__DIR__ . '/' . $user['profile_picture'])): ?>
                        <img src="<?php echo htmlspecialchars($user['profile_picture']); ?>" alt="Profile" class="w-full h-full object-cover">
                    <?php else: ?>
                        <i class="fa-solid fa-user text-4xl text-sky-400"></i>
                    <?php endif; ?>
                </div>
                <div class="min-w-0">
                    <div class="flex items-center gap-3">
                        <h2 class="text-2xl font-bold text-white"><?php echo htmlspecialchars($user['name']); ?></h2>
                        <?php if ($is_own_profile): ?>
                            <button onclick="document.getElementById('editModal').classList.remove('hidden')" class="text-zinc-500 hover:text-sky-400 transition-colors">
                                <i class="fa-solid fa-pen-to-square"></i>
                            </button>
                        <?php endif; ?>
                    </div>
                    <p class="text-zinc-400 mt-0.5"><?php echo htmlspecialchars($user['student_id']); ?></p>
                    <?php if ($is_own_profile): ?>
                        <p class="text-zinc-500 text-sm mt-1"><i class="fa-solid fa-phone mr-1"></i> <?php echo htmlspecialchars($user['contact']); ?></p>
                    <?php endif; ?>
                </div>
                <?php if ($resolve_count > 0): ?>
                <div class="ml-auto shrink-0 flex items-center gap-2 px-4 py-2 rounded-xl bg-amber-500/10 border border-amber-500/20">
                    <i class="fa-solid fa-trophy text-amber-400"></i>
                    <span class="font-bold text-amber-400"><?php echo $resolve_count; ?></span>
                    <span class="text-zinc-400 text-sm">returned</span>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="grid md:grid-cols-2 gap-6">
            <div class="glass-panel rounded-2xl border border-white/10 p-6">
                <h3 class="text-lg font-bold mb-4 flex items-center gap-2">
                    <i class="fa-solid fa-map-pin text-sky-400"></i>
                    Reported items (<?php echo count($reported_active); ?> active)
                </h3>
                <?php if (empty($reported_items)): ?>
                    <p class="text-zinc-500 text-sm">No reports yet.</p>
                <?php else: ?>
                    <ul class="space-y-3 max-h-64 overflow-y-auto">
                        <?php foreach ($reported_items as $it): ?>
                            <li class="p-3 rounded-xl bg-zinc-800/50 border border-zinc-700/50">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="font-medium text-white truncate"><?php echo htmlspecialchars($it['title']); ?></span>
                                    <span class="shrink-0 text-xs px-2 py-0.5 rounded <?php echo $it['item_type'] === 'lost' ? 'bg-red-500/20 text-red-400' : 'bg-emerald-500/20 text-emerald-400'; ?>">
                                        <?php echo $it['item_type']; ?>
                                    </span>
                                </div>
                                <?php if (!empty($it['description'])): ?>
                                    <p class="text-zinc-500 text-xs mt-1 line-clamp-2"><?php echo htmlspecialchars($it['description']); ?></p>
                                <?php endif; ?>
                                <p class="text-zinc-600 text-xs mt-1"><?php echo htmlspecialchars($it['floor']); ?> • <?php echo $it['status'] === 'active' ? 'Active' : 'Resolved'; ?></p>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>

            <div class="glass-panel rounded-2xl border border-white/10 p-6">
                <h3 class="text-lg font-bold mb-4 flex items-center gap-2">
                    <i class="fa-solid fa-hand-holding-heart text-emerald-400"></i>
                    Returned to owners (<?php echo $resolve_count; ?>)
                </h3>
                <?php if (empty($resolved_items)): ?>
                    <p class="text-zinc-500 text-sm">Has not returned any items yet.</p>
                <?php else: ?>
                    <ul class="space-y-3 max-h-64 overflow-y-auto">
                        <?php foreach ($resolved_items as $it): ?>
                            <li class="p-3 rounded-xl bg-zinc-800/50 border border-zinc-700/50">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="font-medium text-white truncate"><?php echo htmlspecialchars($it['title']); ?></span>
                                    <span class="shrink-0 text-xs text-emerald-400">returned</span>
                                </div>
                                <p class="text-zinc-500 text-xs mt-1">to <?php echo htmlspecialchars($it['owner_name']); ?> • <?php echo htmlspecialchars($it['floor']); ?></p>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <footer class="relative z-10 pt-20 pb-10 border-t border-white/5 backdrop-blur-md bg-[#0b0b0b]">
        <div class="max-w-6xl mx-auto px-8">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-12 mb-16">
                <div class="space-y-4">
                    <h3 onclick="window.location.href='index.php'" class="logo-font text-2xl font-bold text-white cursor-pointer">TraceIt<span class="text-sky-500">.</span></h3>
                    <p class="text-zinc-400 text-sm leading-relaxed">
                        The next generation of campus lost and found. Leveraging precision mapping and secure OTP verification to reunite students with their essentials.
                    </p>
                </div>
                <div class="space-y-4">
                    <h4 class="text-xs uppercase tracking-[0.2em] font-bold text-sky-500">Development Team</h4>
                    <ul class="text-zinc-400 text-sm space-y-2">
                        <li class="flex items-center gap-2"><i class="fa-solid fa-terminal text-[10px] text-sky-500/50"></i> Prompter : 25001106 , 25002032 , 25002042</li>
                        <li class="flex items-center gap-2"><i class="fa-solid fa-keyboard text-[10px] text-zinc-600"></i> Typist : 25001140 , 25001221 , 25001181</li>
                        <li class="flex items-center gap-2"><i class="fa-solid fa-vial text-[10px] text-zinc-600"></i> Tester : 25001120 , 25002035 , 25001215</li>
                    </ul>
                </div>
                <div class="space-y-4">
                    <h4 class="text-xs uppercase tracking-[0.2em] font-bold text-sky-500">Project Info</h4>
                    <p class="text-zinc-400 text-sm">
                        Mini Project 2026<br>
                        Computer Science Department<br>
                        Campus Recovery Network v1.0<br>
                        By Group 21.
                    </p>
                </div>
            </div>
            <div class="pt-8 border-t border-white/5 text-center">
                <p class="text-zinc-600 text-[10px] tracking-[0.3em] uppercase font-medium">
                    &copy; <?php echo date("Y"); ?> TraceIt Campus Recovery System. All Rights Reserved.
                </p>
            </div>
        </div>
    </footer>

    <?php if ($is_own_profile): ?>
    <!-- Edit Profile Modal -->
    <div id="editModal" class="hidden fixed inset-0 z-50 flex items-center justify-center px-6">
        <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" onclick="document.getElementById('editModal').classList.add('hidden')"></div>
        <div class="glass-panel w-full max-w-md rounded-2xl border border-white/10 p-8 relative z-10 page-fade-in">
            <h2 class="text-2xl font-bold mb-6 flex items-center gap-2">
                <i class="fa-solid fa-user-pen text-sky-400"></i> Edit Profile
            </h2>
            <form method="POST" enctype="multipart/form-data" class="space-y-5">
                <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo $_SESSION[CSRF_TOKEN_NAME]; ?>">
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-zinc-500 mb-2">Full Name</label>
                    <input type="text" name="name" value="<?php echo htmlspecialchars($user['name']); ?>" required class="w-full bg-zinc-900/50 border border-zinc-800 rounded-xl px-4 py-3 text-white focus:outline-none focus:border-sky-500/50 transition-all">
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-zinc-500 mb-2">Contact Number</label>
                    <input type="text" name="contact" value="<?php echo htmlspecialchars($user['contact']); ?>" required class="w-full bg-zinc-900/50 border border-zinc-800 rounded-xl px-4 py-3 text-white focus:outline-none focus:border-sky-500/50 transition-all">
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-zinc-500 mb-2">Profile Picture (Max 2MB)</label>
                    <?php if (!empty($user['profile_picture']) && file_exists(__DIR__ . '/' . $user['profile_picture'])): ?>
                        <div id="current_pic_display" class="mb-3 flex items-center gap-3 p-2 rounded-xl bg-zinc-900/50 border border-zinc-800">
                            <img src="<?php echo htmlspecialchars($user['profile_picture']); ?>" class="w-10 h-10 rounded-lg object-cover">
                            <span class="text-xs text-zinc-400 flex-1">Current picture</span>
                            <button type="button" onclick="toggleRemovePicture()" id="remove_pic_btn" class="text-xs font-bold text-red-500 hover:text-red-400 px-3 py-1 rounded-lg bg-red-500/10 transition-all">Remove</button>
                        </div>
                    <?php endif; ?>
                    <input type="hidden" name="remove_picture" id="remove_picture_input" value="0">
                    <input type="file" name="profile_picture" id="profile_picture_input" accept="image/jpeg,image/png,image/webp" class="w-full bg-zinc-900/50 border border-zinc-800 rounded-xl px-4 py-3 text-zinc-400 text-sm focus:outline-none focus:border-sky-500/50 transition-all file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-sky-500/10 file:text-sky-400 hover:file:bg-sky-500/20">
                    <p id="remove_status" class="hidden text-[10px] text-red-400 mt-1 font-bold uppercase tracking-widest"><i class="fa-solid fa-circle-info mr-1"></i> Picture will be removed on save</p>
                </div>
                <div class="flex gap-3 pt-2">
                    <button type="button" onclick="closeEditModal()" class="flex-1 bg-zinc-900/50 border border-zinc-800 text-zinc-400 hover:text-white px-6 py-3 rounded-xl font-bold transition-all">Cancel</button>
                    <button type="submit" name="update_profile" class="flex-1 bg-sky-500 hover:bg-sky-400 text-white px-6 py-3 rounded-xl font-bold transition-all shadow-lg shadow-sky-500/20">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <script>
        const glow = document.querySelector('.pointer-glow');
        window.addEventListener('mousemove', (e) => {
            glow.style.left = e.clientX + 'px';
            glow.style.top = e.clientY + 'px';
        });

        function toggleRemovePicture() {
            const input = document.getElementById('remove_picture_input');
            const btn = document.getElementById('remove_pic_btn');
            const status = document.getElementById('remove_status');
            const fileInput = document.getElementById('profile_picture_input');

            if (input.value === '0') {
                input.value = '1';
                btn.innerText = 'Keep';
                btn.classList.replace('text-red-500', 'text-sky-500');
                btn.classList.replace('bg-red-500/10', 'bg-sky-500/10');
                status.classList.remove('hidden');
                fileInput.disabled = true;
                fileInput.classList.add('opacity-50');
            } else {
                input.value = '0';
                btn.innerText = 'Remove';
                btn.classList.replace('text-sky-500', 'text-red-500');
                btn.classList.replace('bg-sky-500/10', 'bg-red-500/10');
                status.classList.add('hidden');
                fileInput.disabled = false;
                fileInput.classList.remove('opacity-50');
            }
        }

        function closeEditModal() {
            document.getElementById('editModal').classList.add('hidden');
            // Reset removal state if they cancel
            const input = document.getElementById('remove_picture_input');
            if (input && input.value === '1') toggleRemovePicture();
            document.getElementById('profile_picture_input').value = '';
        }

        // Close modal on escape
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') closeEditModal();
        });

        // Page Transition Logic
        document.querySelectorAll('a').forEach(link => {
            link.addEventListener('click', e => {
                if (link.hostname === window.location.hostname && !link.hash && !link.href.includes('logout')) {
                    e.preventDefault();
                    document.body.classList.add('page-fade-out');
                    setTimeout(() => window.location.href = link.href, 300);
                }
            });
        });

        window.onpageshow = function(event) {
            if (event.persisted) document.body.classList.remove('page-fade-out');
        };
    </script>
</body>
</html>
