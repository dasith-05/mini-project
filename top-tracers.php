<?php
session_start();

require_once __DIR__ . '/includes/logger.php';
require_once __DIR__ . '/config.php';

try {
    $db = new PDO('sqlite:' . DB_PATH);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    log_error('Database connection failed', ['error' => $e->getMessage()]);
    header('HTTP/1.1 503 Service Unavailable');
    exit('Service temporarily unavailable.');
}

// Require login to view leaderboard
if (empty($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

$stmt = $db->query("
    SELECT u.id, u.name, u.student_id, u.profile_picture, COUNT(i.id) AS resolve_count
    FROM users u
    INNER JOIN items i ON i.resolved_by_user_id = u.id AND i.status = 'resolved'
    GROUP BY u.id
    ORDER BY resolve_count DESC
    LIMIT 50
");
$top_tracers = $stmt->fetchAll(PDO::FETCH_ASSOC);

$rank = 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Top Tracers • TraceIt</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Space+Grotesk:wght@500;600&display=swap');
        body { font-family: 'Inter', sans-serif; }
        .logo-font { font-family: 'Space Grotesk', sans-serif; }
        body { background-color: #0b0b0b; background-image: linear-gradient(rgba(14, 165, 233, 0.08) 1px, transparent 1px), linear-gradient(90deg, rgba(14, 165, 233, 0.08) 1px, transparent 1px); background-size: 40px 40px; }
        .glass-panel { background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(20px); border: 1px solid rgba(255, 255, 255, 0.1); }
        .rank-1 { background: linear-gradient(135deg, #38bdf8 0%, #0ea5e9 100%); }
        .rank-2 { background: linear-gradient(135deg, #7dd3fc 0%, #38bdf8 100%); }
        .rank-3 { background: linear-gradient(135deg, #bae6fd 0%, #7dd3fc 100%); }

        /* Page Transition */
        .page-fade-in { animation: pageFadeIn 0.6s cubic-bezier(0.4, 0, 0.2, 1) forwards; }
        @keyframes pageFadeIn { from { opacity: 0; } to { opacity: 1; } }

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

        .avatar-glow { box-shadow: 0 0 15px rgba(14, 165, 233, 0.2); }
    </style>
</head>
<body class="min-h-screen text-white page-fade-in">
    <div class="pointer-glow"></div>
    <div class="max-w-4xl mx-auto px-6 py-10 relative z-10">
        <div class="flex justify-between items-center mb-10 flex-wrap gap-4">
            <div class="flex items-center gap-4">
                <a href="index.php" class="text-zinc-400 hover:text-white transition-colors">
                    <i class="fa-solid fa-arrow-left text-xl"></i>
                </a>
                <h1 class="logo-font text-4xl font-bold tracking-tighter bg-clip-text text-transparent bg-gradient-to-b from-white to-sky-500/50">
                    Top Tracers
                </h1>
            </div>
            <div class="flex items-center gap-3">
                <a href="profile.php?id=<?php echo (int) $_SESSION['user_id']; ?>" class="bg-zinc-900/50 border border-zinc-800 text-sky-400 hover:text-sky-300 hover:shadow-[0_0_15px_rgba(14,165,233,0.4)] hover:border-sky-500/50 px-4 py-2.5 rounded-xl transition-all text-sm font-bold flex items-center gap-2">
                    <i class="fa-solid fa-circle-user"></i> My Profile
                </a>
                <a href="index.php?logout=1" class="bg-zinc-900/50 border border-zinc-800 text-red-500 hover:text-red-400 hover:shadow-[0_0_15px_rgba(239,68,68,0.4)] hover:border-red-500/50 px-4 py-2.5 rounded-xl transition-all text-sm font-bold flex items-center gap-2">
                    <i class="fa-solid fa-power-off"></i> Logout
                </a>
            </div>
        </div>

        <p class="text-zinc-400 mb-8">Users who have returned the most items to their owners. Thank you for helping the campus!</p>

        <div class="glass-panel rounded-2xl border border-white/10 overflow-hidden">
            <?php if (empty($top_tracers)): ?>
                <div class="p-12 text-center text-zinc-500">
                    <i class="fa-solid fa-trophy text-5xl mb-4 opacity-50"></i>
                    <p class="text-lg font-medium">No tracers yet</p>
                    <p class="text-sm mt-1">Be the first to return an item and claim a spot here.</p>
                    <a href="index.php" class="inline-block mt-4 bg-sky-500 hover:bg-sky-400 text-white px-6 py-3 rounded-xl font-bold">Go to map</a>
                </div>
            <?php else: ?>
                <ul class="divide-y divide-white/5">
                    <?php foreach ($top_tracers as $row): $rank++; ?>
                        <li class="flex items-center gap-6 p-5 hover:bg-white/5 transition-colors">
                            <div class="relative shrink-0">
                                <div class="w-12 h-12 rounded-xl flex items-center justify-center text-xl font-bold shrink-0
                                    <?php
                                    if ($rank === 1) echo 'rank-1 text-black';
                                    elseif ($rank === 2) echo 'rank-2 text-white';
                                    elseif ($rank === 3) echo 'rank-3 text-amber-100';
                                    else echo 'bg-zinc-700/80 text-zinc-300';
                                    ?>">
                                    <?php echo $rank; ?>
                                </div>
                                <div class="absolute -bottom-1 -right-1 w-8 h-8 rounded-lg overflow-hidden border-2 border-[#0b0b0b] bg-zinc-800 avatar-glow">
                                    <?php if (!empty($row['profile_picture']) && file_exists(__DIR__ . '/' . $row['profile_picture'])): ?>
                                        <img src="<?php echo htmlspecialchars($row['profile_picture']); ?>" class="w-full h-full object-cover">
                                    <?php else: ?>
                                        <div class="w-full h-full flex items-center justify-center bg-sky-500/10">
                                            <i class="fa-solid fa-user text-[10px] text-sky-400"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="flex-1 min-w-0">
                                <a href="profile.php?id=<?php echo (int) $row['id']; ?>" class="font-bold text-white hover:text-sky-400 hover:underline truncate block">
                                    <?php echo htmlspecialchars($row['name']); ?>
                                </a>
                                <p class="text-zinc-500 text-sm"><?php echo htmlspecialchars($row['student_id']); ?></p>
                            </div>
                            <div class="text-right shrink-0">
                                <span class="text-2xl font-bold text-emerald-400"><?php echo (int) $row['resolve_count']; ?></span>
                                <span class="text-zinc-500 text-sm block">item<?php echo (int)$row['resolve_count'] !== 1 ? 's' : ''; ?> returned</span>
                            </div>
                            <a href="profile.php?id=<?php echo (int) $row['id']; ?>" class="text-sky-400 hover:text-sky-300 text-sm font-medium shrink-0">
                                View profile <i class="fa-solid fa-chevron-right text-xs"></i>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
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
    
    <script>
        const glow = document.querySelector('.pointer-glow');
        window.addEventListener('mousemove', (e) => {
            glow.style.left = e.clientX + 'px';
            glow.style.top = e.clientY + 'px';
        });
    </script>
</body>
</html>
