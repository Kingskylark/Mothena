<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$page_title = 'Content';
$current_user = getCurrentUser();
$personalized_content = getPersonalizedContent($current_user['id'], 20);

include 'includes/header.php';
?>

<style>
    .content-card {
        transition: all 0.3s ease;
        cursor: pointer;
    }

    .content-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
    }

    .card-img-top {
        transition: transform 0.3s ease;
    }

    .content-card:hover .card-img-top {
        transform: scale(1.05);
    }

    .expanded-content {
        max-height: 0;
        overflow: hidden;
        transition: max-height 0.3s ease-out;
    }

    .expanded-content.show {
        max-height: 1000px;
        transition: max-height 0.5s ease-in;
    }

    .full-content {
        padding: 15px 0;
        border-top: 1px solid #dee2e6;
        margin-top: 15px;
    }

    .toggle-btn {
        transition: all 0.2s ease;
    }

    .toggle-btn:hover {
        transform: translateY(-1px);
    }

    .video-container {
        position: relative;
        width: 100%;
        height: 300px;
        margin-bottom: 15px;
    }

    .audio-container {
        background: #f8f9fa;
        padding: 20px;
        border-radius: 8px;
        text-align: center;
        margin-bottom: 15px;
    }
</style>

<div class="container mt-4">
    <h2 class="mb-4">Recommended Content</h2>

    <div class="row">
        <?php if (!empty($personalized_content)): ?>
            <?php foreach ($personalized_content as $content): ?>
                <div class="col-md-6 mb-4">
                    <div class="card content-card h-100" id="card-<?php echo $content['id']; ?>">
                        <!-- Media Section (Image or Video) -->
                        <div class="card-img-top-container" style="height: 200px; overflow: hidden;">
                            <?php if ($content['content_type'] === 'video'): ?>
                                <?php
                                $video_url = $content['content_text'];
                                $embed_code = '';

                                // Check if it's a YouTube URL
                                if (preg_match('/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/', $video_url, $matches)) {
                                    $video_id = $matches[1];
                                    $embed_code = "https://www.youtube.com/embed/$video_id";
                                }
                                // Check if it's a Vimeo URL
                                elseif (preg_match('/vimeo\.com\/(\d+)/', $video_url, $matches)) {
                                    $video_id = $matches[1];
                                    $embed_code = "https://player.vimeo.com/video/$video_id";
                                }
                                // Check if it's a local video file
                                elseif (preg_match('/\.(mp4|webm|ogg)$/i', $video_url)) {
                                    $embed_code = 'local';
                                }
                                ?>

                                <!-- Video thumbnail/preview -->
                                <div class="video-preview-container" style="position: relative; width: 100%; height: 100%;">
                                    <?php if (!empty($content['image_url'])): ?>
                                        <img src="<?php echo htmlspecialchars($content['image_url']); ?>" class="card-img-top"
                                            alt="<?php echo htmlspecialchars($content['title']); ?>"
                                            style="width: 100%; height: 100%; object-fit: cover;">
                                    <?php else: ?>
                                        <div class="d-flex align-items-center justify-content-center h-100 bg-dark">
                                            <i class="fas fa-play-circle fa-4x text-white opacity-75"></i>
                                        </div>
                                    <?php endif; ?>
                                    <div class="position-absolute top-50 start-50 translate-middle">
                                        <i class="fas fa-play-circle fa-3x text-white"
                                            style="text-shadow: 0 0 10px rgba(0,0,0,0.5);"></i>
                                    </div>
                                </div>

                            <?php elseif ($content['content_type'] === 'audio'): ?>
                                <!-- Audio content display -->
                                <div class="d-flex align-items-center justify-content-center h-100 bg-light">
                                    <div class="text-center">
                                        <i class="fas fa-headphones fa-3x text-primary mb-2"></i>
                                        <p class="text-muted mb-2">Audio Content</p>
                                        <small class="text-muted">Click below to listen</small>
                                    </div>
                                </div>
                            <?php else: ?>
                                <!-- Regular image for non-video/audio content -->
                                <?php
                                $image_url = !empty($content['image_url']) ? $content['image_url'] : 'assets/images/default.jpeg';
                                // If no image is set, use a category-based default
                                if (empty($content['image_url'])) {
                                    switch (strtolower($content['category'])) {
                                        case 'nutrition':
                                        case 'mental_health':
                                        case 'exercise':
                                        case 'labor_prep':
                                        default:
                                            $image_url = 'assets/images/default.jpeg';
                                    }
                                }
                                ?>
                                <img src="<?php echo htmlspecialchars($image_url); ?>" class="card-img-top"
                                    alt="<?php echo htmlspecialchars($content['title']); ?>"
                                    style="width: 100%; height: 100%; object-fit: cover;"
                                    onerror="this.src='assets/images/default-content.jpg'">
                            <?php endif; ?>
                        </div>

                        <div class="card-header d-flex justify-content-between align-items-center">
                            <small class="text-muted">
                                <i class="fas fa-tag"></i> <?php echo ucwords($content['category']); ?>
                            </small>
                            <?php if ($content['is_featured']): ?>
                                <span class="badge bg-warning">
                                    <i class="fas fa-star"></i> Featured
                                </span>
                            <?php endif; ?>
                        </div>

                        <div class="card-body d-flex flex-column">
                            <h5 class="card-title"><?php echo htmlspecialchars($content['title']); ?></h5>

                            <!-- Preview content -->
                            <?php if ($content['content_type'] === 'video' || $content['content_type'] === 'audio'): ?>
                                <p class="card-text flex-grow-1">
                                    <?php
                                    $description = !empty($content['description']) ? $content['description'] :
                                        ($content['content_type'] === 'video' ? 'Watch this video for helpful pregnancy tips and information.' : 'Listen to this audio content for helpful pregnancy guidance.');
                                    echo substr(htmlspecialchars($description), 0, 120) . '...';
                                    ?>
                                </p>
                            <?php else: ?>
                                <p class="card-text flex-grow-1">
                                    <?php echo substr(htmlspecialchars($content['content_text']), 0, 120) . '...'; ?></p>
                            <?php endif; ?>

                            <!-- Expanded content (hidden by default) -->
                            <div class="expanded-content" id="expanded-<?php echo $content['id']; ?>">
                                <div class="full-content">
                                    <?php if ($content['content_type'] === 'video'): ?>
                                        <!-- Full video content -->
                                        <?php if ($embed_code === 'local'): ?>
                                            <div class="video-container">
                                                <video controls style="width: 100%; height: 100%; object-fit: cover;"
                                                    poster="<?php echo !empty($content['image_url']) ? htmlspecialchars($content['image_url']) : 'assets/images/default.jpeg'; ?>">
                                                    <source src="<?php echo htmlspecialchars($video_url); ?>" type="video/mp4">
                                                    <p>Your browser doesn't support HTML5 video. <a
                                                            href="<?php echo htmlspecialchars($video_url); ?>">Download the video</a>
                                                        instead.</p>
                                                </video>
                                            </div>
                                        <?php elseif (!empty($embed_code)): ?>
                                            <div class="video-container">
                                                <iframe src="<?php echo htmlspecialchars($embed_code); ?>"
                                                    style="width: 100%; height: 100%; border: none;" frameborder="0"
                                                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                                                    allowfullscreen>
                                                </iframe>
                                            </div>
                                        <?php else: ?>
                                            <div class="alert alert-info">
                                                <i class="fas fa-video"></i>
                                                <a href="<?php echo htmlspecialchars($video_url); ?>" target="_blank"
                                                    class="alert-link">
                                                    Click here to watch the video
                                                </a>
                                            </div>
                                        <?php endif; ?>

                                        <?php if (!empty($content['description'])): ?>
                                            <div class="mt-3">
                                                <h6><i class="fas fa-info-circle"></i> Description:</h6>
                                                <p><?php echo nl2br(htmlspecialchars($content['description'])); ?></p>
                                            </div>
                                        <?php endif; ?>

                                    <?php elseif ($content['content_type'] === 'audio'): ?>
                                        <!-- Full audio content -->
                                        <div class="audio-container">
                                            <?php if (!empty($content['content_text']) && preg_match('/\.(mp3|wav|ogg|m4a)$/i', $content['content_text'])): ?>
                                                <div class="mb-3">
                                                    <i class="fas fa-headphones fa-2x text-primary mb-2"></i>
                                                </div>
                                                <audio controls style="width: 100%; max-width: 400px;">
                                                    <source src="<?php echo htmlspecialchars($content['content_text']); ?>"
                                                        type="audio/mpeg">
                                                    Your browser does not support the audio element.
                                                </audio>
                                            <?php else: ?>
                                                <div class="mb-3">
                                                    <i class="fas fa-headphones fa-2x text-primary mb-2"></i>
                                                </div>
                                                <a href="<?php echo htmlspecialchars($content['content_text']); ?>" target="_blank"
                                                    class="btn btn-primary">
                                                    <i class="fas fa-external-link-alt"></i> Listen to Audio
                                                </a>
                                            <?php endif; ?>
                                        </div>

                                        <?php if (!empty($content['description'])): ?>
                                            <div class="mt-3">
                                                <h6><i class="fas fa-info-circle"></i> Description:</h6>
                                                <p><?php echo nl2br(htmlspecialchars($content['description'])); ?></p>
                                            </div>
                                        <?php endif; ?>

                                    <?php else: ?>
                                        <!-- Full text content for articles, tips, etc. -->
                                        <div class="content-text">
                                            <?php echo nl2br(htmlspecialchars($content['content_text'])); ?>
                                        </div>
                                    <?php endif; ?>

                                    <!-- Additional metadata -->
                                    <div class="mt-4 pt-3 border-top">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <small class="text-muted d-block mb-1">
                                                    <i class="fas fa-calendar"></i> <strong>Trimester:</strong>
                                                    <?php echo $content['trimester']; ?>
                                                </small>
                                                <small class="text-muted d-block mb-1">
                                                    <i class="fas fa-tag"></i> <strong>Category:</strong>
                                                    <?php echo ucwords($content['category']); ?>
                                                </small>
                                            </div>
                                            <div class="col-md-6">
                                                <small class="text-muted d-block mb-1">
                                                    <?php
                                                    $type_icons = [
                                                        'article' => 'fas fa-newspaper',
                                                        'tip' => 'fas fa-lightbulb',
                                                        'video' => 'fas fa-video',
                                                        'audio' => 'fas fa-headphones',
                                                        'checklist' => 'fas fa-check-square'
                                                    ];
                                                    $icon = $type_icons[$content['content_type']] ?? 'fas fa-file';
                                                    ?>
                                                    <i class="<?php echo $icon; ?>"></i> <strong>Type:</strong>
                                                    <?php echo ucwords($content['content_type']); ?>
                                                </small>
                                                <small class="text-muted d-block view-count-display">
                                                    <i class="fas fa-eye"></i> <strong>Views:</strong>
                                                    <?php echo number_format($content['view_count']); ?>
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Content metadata -->
                            <div class="mb-2">
                                <small class="text-muted">
                                    <i class="fas fa-calendar"></i> Trimester <?php echo $content['trimester']; ?>
                                    <span class="mx-2">|</span>
                                    <?php
                                    $type_icons = [
                                        'article' => 'fas fa-newspaper',
                                        'tip' => 'fas fa-lightbulb',
                                        'video' => 'fas fa-video',
                                        'audio' => 'fas fa-headphones',
                                        'checklist' => 'fas fa-check-square'
                                    ];
                                    $icon = $type_icons[$content['content_type']] ?? 'fas fa-file';
                                    ?>
                                    <i class="<?php echo $icon; ?>"></i> <?php echo ucwords($content['content_type']); ?>
                                </small>
                            </div>

                            <div class="d-flex justify-content-between align-items-center mt-auto">
                                <small class="text-muted view-count-display">
                                    <i class="fas fa-eye"></i> <?php echo number_format($content['view_count']); ?> views
                                </small>
                                <button class="btn btn-primary btn-sm toggle-btn"
                                    onclick="toggleContent(<?php echo $content['id']; ?>, '<?php echo $content['content_type']; ?>', this)">
                                    <?php if ($content['content_type'] === 'video'): ?>
                                        <i class="fas fa-play"></i> <span class="btn-text">Watch Now</span>
                                    <?php elseif ($content['content_type'] === 'audio'): ?>
                                        <i class="fas fa-headphones"></i> <span class="btn-text">Listen</span>
                                    <?php else: ?>
                                        <i class="fas fa-book-open"></i> <span class="btn-text">Read More</span>
                                    <?php endif; ?>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="col-12">
                <div class="card">
                    <div class="card-body text-center py-5">
                        <i class="fas fa-book fa-3x text-muted mb-3"></i>
                        <h5>No content found</h5>
                        <p class="text-muted">Please update your profile to receive better recommendations.</p>
                        <a href="profile.php" class="btn btn-primary">
                            <i class="fas fa-user-edit"></i> Update Profile
                        </a>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
    function toggleContent(contentId, contentType, button) {
        const expandedContent = document.getElementById('expanded-' + contentId);
        const buttonText = button.querySelector('.btn-text');
        const buttonIcon = button.querySelector('i');

        if (expandedContent.classList.contains('show')) {
            // Collapse the content
            expandedContent.classList.remove('show');

            // Reset button text and icon
            if (contentType === 'video') {
                buttonIcon.className = 'fas fa-play';
                buttonText.textContent = 'Watch Now';
            } else if (contentType === 'audio') {
                buttonIcon.className = 'fas fa-headphones';
                buttonText.textContent = 'Listen';
            } else {
                buttonIcon.className = 'fas fa-book-open';
                buttonText.textContent = 'Read More';
            }

            button.classList.remove('btn-outline-primary');
            button.classList.add('btn-primary');

        } else {
            // Expand the content
            expandedContent.classList.add('show');

            // Update button to show "collapse" state
            buttonIcon.className = 'fas fa-chevron-up';
            buttonText.textContent = 'Show Less';

            button.classList.remove('btn-primary');
            button.classList.add('btn-outline-primary');

            // Update view count
            updateViewCount(contentId);

            // Smooth scroll to the expanded content
            setTimeout(() => {
                expandedContent.scrollIntoView({
                    behavior: 'smooth',
                    block: 'nearest'
                });
            }, 300);
        }
    }

    function updateViewCount(contentId) {
        fetch('ajax/update_view_count.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'content_id=' + encodeURIComponent(contentId)
        })
            .then(async response => {
                const text = await response.text();  // read raw response text
                console.log('Raw response:', text);

                try {
                    const data = JSON.parse(text);   // manually parse JSON
                    if (data.success) {
                        console.log('View count updated successfully');
                        updateViewCountDisplay(contentId, data.new_count);
                    } else {
                        throw new Error(data.message || 'Failed to update view count');
                    }
                } catch (err) {
                    console.error('Invalid JSON response:', err);
                }
            })
            .catch(error => {
                console.log('AJAX view count update failed:', error);

                // Method 2: Fallback - Use image pixel tracking
                const img = new Image();
                img.src = `ajax/track_view.php?content_id=${encodeURIComponent(contentId)}&t=${Date.now()}`;

                // Method 3: Store in localStorage for batch processing later
                storeViewForBatchUpdate(contentId);
            });
    }

    function updateViewCountDisplay(contentId, newCount) {
        // Update the view count display in the UI
        const card = document.getElementById('card-' + contentId);
        if (card) {
            const viewElements = card.querySelectorAll('.view-count-display');
            viewElements.forEach(element => {
                element.textContent = new Intl.NumberFormat().format(newCount) + ' views';
            });
        }
    }


    // Add smooth scrolling and enhanced interactions
    document.addEventListener('DOMContentLoaded', function () {
        const contentCards = document.querySelectorAll('.content-card');

        contentCards.forEach(card => {
            // Add click to expand functionality (optional - click anywhere on card)
            card.addEventListener('click', function (e) {
                // Only trigger if not clicking on button or interactive elements
                if (!e.target.closest('button') && !e.target.closest('a') && !e.target.closest('iframe') && !e.target.closest('video') && !e.target.closest('audio')) {
                    const button = this.querySelector('.toggle-btn');
                    if (button) {
                        button.click();
                    }
                }
            });
        });

    });

    // Process pending views when page becomes visible (user returns to tab)
    document.addEventListener('visibilitychange', function () {
        if (!document.hidden) {
            processPendingViews();
        }
    });

    // Process pending views before page unload
    window.addEventListener('beforeunload', function () {
        processPendingViews();
    });
</script>

<?php include 'includes/footer.php'; ?>