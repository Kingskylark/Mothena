<?php
require_once '../includes/config.php';
require_once '../includes/admin_functions.php';

if (!isAdminLoggedIn())
    redirect('login.php');

$page_title = 'Manage Content';
$search = sanitize($_GET['search'] ?? '');
$page = max(1, (int) ($_GET['page'] ?? 1));
$per_page = 10;

// Handle delete
if (isset($_GET['delete_id'])) {
    $id = (int) $_GET['delete_id'];
    $conn->query("DELETE FROM content WHERE id = $id");
    redirect("content.php?page=$page&search=" . urlencode($search));
}

// Handle Add
if ($_POST && isset($_POST['add_content'])) {
    $data = [
        'title' => sanitize($_POST['title']),
        'description' => sanitize($_POST['description'] ?? ''),
        'content_text' => $conn->real_escape_string($_POST['content_text']),
        'image_url' => sanitize($_POST['image_url'] ?? ''),
        'category' => sanitize($_POST['category']),
        'trimester' => (int) $_POST['trimester'],
        'age_group' => sanitize($_POST['age_group']),
        'content_type' => sanitize($_POST['content_type']),
        'language' => sanitize($_POST['language'] ?? 'english'),
        'is_featured' => isset($_POST['is_featured']) ? 1 : 0,
        'created_at' => date('Y-m-d H:i:s'),
        'view_count' => 0
    ];
    createContent($data);
    redirect("content.php");
}

// Handle Edit
if ($_POST && isset($_POST['update_content'])) {
    $id = (int) $_POST['content_id'];
    $fields = [
        'title' => sanitize($_POST['title']),
        'description' => sanitize($_POST['description'] ?? ''),
        'content_text' => $conn->real_escape_string($_POST['content_text']),
        'image_url' => sanitize($_POST['image_url'] ?? ''),
        'category' => sanitize($_POST['category']),
        'trimester' => (int) $_POST['trimester'],
        'age_group' => sanitize($_POST['age_group']),
        'content_type' => sanitize($_POST['content_type']),
        'language' => sanitize($_POST['language'] ?? 'english'),
        'is_featured' => isset($_POST['is_featured']) ? 1 : 0
    ];
    updateContent($id, $fields);
    redirect("content.php?page=$page&search=" . urlencode($search));
}

$content_items = getAllContent($page, $per_page, $search);
$total_items = getTotalContent($search);
$total_pages = ceil($total_items / $per_page);

include '../includes/admin_header.php';
?>

<div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2>Content Management</h2>
        <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addContentModal">
            <i class="fas fa-plus"></i> Add New Content
        </button>
    </div>

    <form method="GET" class="d-flex mb-3">
        <input type="text" name="search" class="form-control me-2" placeholder="Search content..."
            value="<?php echo htmlspecialchars($search); ?>">
        <button class="btn btn-primary">
            <i class="fas fa-search"></i> Search
        </button>
    </form>

    <div class="table-responsive">
        <table class="table table-bordered table-striped align-middle">
            <thead class="table-dark">
                <tr>
                    <th>Title</th>
                    <th>Category</th>
                    <th>Trimester</th>
                    <th>Age Group</th>
                    <th>Type</th>
                    <th>Views</th>
                    <th>Featured</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($content_items as $item): ?>
                    <tr>

                        <td>
                            <strong><?php echo htmlspecialchars($item['title']); ?></strong>
                            <?php if (!empty($item['description'])): ?>
                                <br><small
                                    class="text-muted"><?php echo substr(htmlspecialchars($item['description']), 0, 50) . '...'; ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge bg-secondary"><?php echo ucwords($item['category']); ?></span>
                        </td>
                        <td><?php echo $item['trimester']; ?></td>
                        <td><?php echo ucwords(str_replace('_', ' ', $item['age_group'])); ?></td>
                        <td>
                            <?php
                            $type_icons = [
                                'article' => 'fas fa-newspaper',
                                'tip' => 'fas fa-lightbulb',
                                'video' => 'fas fa-video',
                                'audio' => 'fas fa-headphones',
                                'checklist' => 'fas fa-check-square'
                            ];
                            $icon = $type_icons[$item['content_type']] ?? 'fas fa-file';
                            ?>
                            <i class="<?php echo $icon; ?>"></i> <?php echo ucfirst($item['content_type']); ?>
                        </td>
                        <td><?php echo number_format($item['view_count']); ?></td>
                        <td>
                            <?php if ($item['is_featured']): ?>
                                <span class="badge bg-warning"><i class="fas fa-star"></i> Yes</span>
                            <?php else: ?>
                                <span class="badge bg-light text-dark">No</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <button class="btn btn-sm btn-primary" data-bs-toggle="modal"
                                data-bs-target="#editContentModal<?php echo $item['id']; ?>">
                                <i class="fas fa-edit"></i> Edit
                            </button>
                            <a href="?delete_id=<?php echo $item['id']; ?>&page=<?php echo $page; ?>&search=<?php echo urlencode($search); ?>"
                                class="btn btn-sm btn-danger"
                                onclick="return confirm('Are you sure you want to delete this content?');">
                                <i class="fas fa-trash"></i> Delete
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Edit Modals -->
    <?php foreach ($content_items as $item): ?>
        <div class="modal fade" id="editContentModal<?php echo $item['id']; ?>" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <form method="POST" class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="fas fa-edit"></i> Edit Content
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="content_id" value="<?php echo $item['id']; ?>">

                        <div class="row">
                            <div class="col-md-8 mb-3">
                                <label for="title_<?php echo $item['id']; ?>" class="form-label">Title *</label>
                                <input class="form-control" name="title" id="title_<?php echo $item['id']; ?>"
                                    value="<?php echo htmlspecialchars($item['title']); ?>" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="language_<?php echo $item['id']; ?>" class="form-label">Language</label>
                                <select name="language" id="language_<?php echo $item['id']; ?>" class="form-control">
                                    <option value="english" <?php if ($item['language'] == 'english')
                                        echo 'selected'; ?>>
                                        English</option>
                                    <option value="hausa" <?php if ($item['language'] == 'hausa')
                                        echo 'selected'; ?>>Hausa
                                    </option>
                                    <option value="yoruba" <?php if ($item['language'] == 'yoruba')
                                        echo 'selected'; ?>>Yoruba
                                    </option>
                                    <option value="igbo" <?php if ($item['language'] == 'igbo')
                                        echo 'selected'; ?>>Igbo
                                    </option>
                                </select>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="description_<?php echo $item['id']; ?>" class="form-label">Description</label>
                            <textarea class="form-control" name="description" id="description_<?php echo $item['id']; ?>"
                                rows="2"
                                placeholder="Brief description of the content..."><?php echo htmlspecialchars($item['description'] ?? ''); ?></textarea>
                        </div>

                        <div class="mb-3">
                            <label for="image_url_<?php echo $item['id']; ?>" class="form-label">Image URL</label>
                            <input type="url" class="form-control" name="image_url"
                                id="image_url_<?php echo $item['id']; ?>"
                                value="<?php echo htmlspecialchars($item['image_url'] ?? ''); ?>"
                                placeholder="https://example.com/image.jpg or assets/images/image.jpg">
                            <div class="form-text">
                                <i class="fas fa-info-circle"></i> You can use external URLs or local paths (e.g.,
                                assets/images/filename.jpg)
                            </div>
                            <?php if (!empty($item['image_url'])): ?>
                                <div class="mt-2">
                                    <small class="text-muted">Current image:</small><br>
                                    <img src="../<?php echo htmlspecialchars($item['image_url']); ?>" alt="Current image"
                                        style="max-width: 200px; max-height: 100px; object-fit: cover; border-radius: 4px;"
                                        onerror="this.src='../assets/images/default.jpeg'">
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="mb-3">
                            <label for="category_<?php echo $item['id']; ?>" class="form-label">Category *</label>
                            <select name="category" id="category_<?php echo $item['id']; ?>" class="form-control" required>
                                <option value="">Select Category</option>
                                <option value="nutrition" <?php if ($item['category'] == 'nutrition')
                                    echo 'selected'; ?>>
                                    Nutrition</option>
                                <option value="mental_health" <?php if ($item['category'] == 'mental_health')
                                    echo 'selected'; ?>>Mental Health</option>
                                <option value="exercise" <?php if ($item['category'] == 'exercise')
                                    echo 'selected'; ?>>
                                    Exercise</option>
                                <option value="labor_prep" <?php if ($item['category'] == 'labor_prep')
                                    echo 'selected'; ?>>
                                    Labor Preparation</option>
                                <option value="prenatal_care" <?php if ($item['category'] == 'prenatal_care')
                                    echo 'selected'; ?>>Prenatal Care</option>
                                <option value="general" <?php if ($item['category'] == 'general')
                                    echo 'selected'; ?>>General
                                </option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="content_text_<?php echo $item['id']; ?>" class="form-label">Content *</label>
                            <div id="content_field_<?php echo $item['id']; ?>">
                                <?php if ($item['content_type'] == 'video'): ?>
                                    <input type="url" class="form-control" name="content_text"
                                        id="content_text_<?php echo $item['id']; ?>"
                                        value="<?php echo htmlspecialchars($item['content_text']); ?>" required
                                        placeholder="Enter YouTube URL, Vimeo URL, or video file path (e.g., assets/videos/video.mp4)">
                                    <div class="form-text">
                                        <i class="fas fa-info-circle"></i> Supported: YouTube, Vimeo URLs, or local video files
                                    </div>
                                <?php elseif ($item['content_type'] == 'audio'): ?>
                                    <input type="url" class="form-control" name="content_text"
                                        id="content_text_<?php echo $item['id']; ?>"
                                        value="<?php echo htmlspecialchars($item['content_text']); ?>" required
                                        placeholder="Enter audio file URL or path (e.g., assets/audio/audio.mp3)">
                                    <div class="form-text">
                                        <i class="fas fa-info-circle"></i> Supported: MP3, WAV, OGG audio files
                                    </div>
                                <?php else: ?>
                                    <textarea class="form-control" name="content_text"
                                        id="content_text_<?php echo $item['id']; ?>" rows="8"
                                        required><?php echo htmlspecialchars($item['content_text']); ?></textarea>
                                    <div class="form-text">
                                        <i class="fas fa-info-circle"></i> Enter the full text content, tips, or checklist items
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <label for="trimester_<?php echo $item['id']; ?>" class="form-label">Trimester *</label>
                                <select name="trimester" id="trimester_<?php echo $item['id']; ?>" class="form-control"
                                    required>
                                    <option value="1" <?php if ($item['trimester'] == 1)
                                        echo 'selected'; ?>>First Trimester
                                    </option>
                                    <option value="2" <?php if ($item['trimester'] == 2)
                                        echo 'selected'; ?>>Second Trimester
                                    </option>
                                    <option value="3" <?php if ($item['trimester'] == 3)
                                        echo 'selected'; ?>>Third Trimester
                                    </option>
                                </select>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label for="age_group_<?php echo $item['id']; ?>" class="form-label">Age Group</label>
                                <select name="age_group" id="age_group_<?php echo $item['id']; ?>" class="form-control">
                                    <option value="all" <?php if ($item['age_group'] == 'all')
                                        echo 'selected'; ?>>All Ages
                                    </option>
                                    <option value="teen" <?php if ($item['age_group'] == 'teen')
                                        echo 'selected'; ?>>Teen
                                        (13-19)</option>
                                    <option value="young_adult" <?php if ($item['age_group'] == 'young_adult')
                                        echo 'selected'; ?>>Young Adult (20-29)</option>
                                    <option value="adult" <?php if ($item['age_group'] == 'adult')
                                        echo 'selected'; ?>>Adult
                                        (30-39)</option>
                                    <option value="mature_adult" <?php if ($item['age_group'] == 'mature_adult')
                                        echo 'selected'; ?>>Mature Adult (40+)</option>
                                </select>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label for="content_type_<?php echo $item['id']; ?>" class="form-label">Content Type
                                    *</label>
                                <select name="content_type" id="content_type_<?php echo $item['id']; ?>"
                                    class="form-control content-type-select" required
                                    onchange="updateContentField('<?php echo $item['id']; ?>', this.value)">
                                    <option value="article" <?php if ($item['content_type'] == 'article')
                                        echo 'selected'; ?>>
                                        📰 Article
                                    </option>
                                    <option value="tip" <?php if ($item['content_type'] == 'tip')
                                        echo 'selected'; ?>>
                                        💡 Tip
                                    </option>
                                    <option value="video" <?php if ($item['content_type'] == 'video')
                                        echo 'selected'; ?>>
                                        🎥 Video
                                    </option>
                                    <option value="audio" <?php if ($item['content_type'] == 'audio')
                                        echo 'selected'; ?>>
                                        🎧 Audio
                                    </option>
                                    <option value="checklist" <?php if ($item['content_type'] == 'checklist')
                                        echo 'selected'; ?>>
                                        ✅ Checklist
                                    </option>
                                </select>
                            </div>
                            <div class="col-md-3 mb-3 d-flex align-items-end">
                                <div class="form-check">
                                    <input type="checkbox" name="is_featured" id="is_featured_<?php echo $item['id']; ?>"
                                        class="form-check-input" <?php echo $item['is_featured'] ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="is_featured_<?php echo $item['id']; ?>">
                                        <i class="fas fa-star text-warning"></i> Featured Content
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button name="update_content" class="btn btn-primary">
                            <i class="fas fa-save"></i> Update Content
                        </button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    <?php endforeach; ?>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
        <nav aria-label="Content pagination">
            <ul class="pagination justify-content-center">
                <?php if ($page > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>">
                            <i class="fas fa-chevron-left"></i> Previous
                        </a>
                    </li>
                <?php endif; ?>

                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                    <li class="page-item <?php if ($i == $page)
                        echo 'active'; ?>">
                        <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>">
                            <?php echo $i; ?>
                        </a>
                    </li>
                <?php endfor; ?>

                <?php if ($page < $total_pages): ?>
                    <li class="page-item">
                        <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>">
                            Next <i class="fas fa-chevron-right"></i>
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </nav>
    <?php endif; ?>
</div>

<!-- Add Content Modal -->
<div class="modal fade" id="addContentModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form method="POST" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-plus"></i> Add New Content
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-8 mb-3">
                        <label for="title" class="form-label">Title *</label>
                        <input class="form-control" name="title" id="title" required
                            placeholder="Enter content title...">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="language" class="form-label">Language</label>
                        <select name="language" id="language" class="form-control">
                            <option value="english">English</option>
                            <option value="hausa">Hausa</option>
                            <option value="yoruba">Yoruba</option>
                            <option value="igbo">Igbo</option>
                        </select>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="description" class="form-label">Description</label>
                    <textarea class="form-control" name="description" id="description" rows="2"
                        placeholder="Brief description of the content..."></textarea>
                </div>

                <div class="mb-3">
                    <label for="image_url" class="form-label">Image URL</label>
                    <input type="url" class="form-control" name="image_url" id="image_url"
                        placeholder="https://example.com/image.jpg or assets/images/image.jpg">
                    <div class="form-text">
                        <i class="fas fa-info-circle"></i> You can use external URLs or local paths (e.g.,
                        assets/images/filename.jpg)
                    </div>
                </div>

                <div class="mb-3">
                    <label for="category" class="form-label">Category *</label>
                    <select name="category" id="category" class="form-control" required>
                        <option value="">Select Category</option>
                        <option value="nutrition">Nutrition</option>
                        <option value="mental_health">Mental Health</option>
                        <option value="exercise">Exercise</option>
                        <option value="labor_prep">Labor Preparation</option>
                        <option value="prenatal_care">Prenatal Care</option>
                        <option value="general">General</option>
                    </select>
                </div>

                <div class="mb-3">
                    <label for="content_text" class="form-label">Content *</label>
                    <div id="content_field_add">
                        <textarea class="form-control" name="content_text" id="content_text" rows="8" required
                            placeholder="Enter the main content here..."></textarea>
                        <div class="form-text">
                            <i class="fas fa-info-circle"></i> Enter the full text content, tips, or checklist items
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-3 mb-3">
                        <label for="trimester" class="form-label">Trimester *</label>
                        <select name="trimester" id="trimester" class="form-control" required>
                            <option value="1">First Trimester</option>
                            <option value="2">Second Trimester</option>
                            <option value="3">Third Trimester</option>
                        </select>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label for="age_group" class="form-label">Age Group</label>
                        <select name="age_group" id="age_group" class="form-control">
                            <option value="all">All Ages</option>
                            <option value="teen">Teen (13-19)</option>
                            <option value="young_adult">Young Adult (20-29)</option>
                            <option value="adult">Adult (30-39)</option>
                            <option value="mature_adult">Mature Adult (40+)</option>
                        </select>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label for="content_type" class="form-label">Content Type *</label>
                        <select name="content_type" id="content_type" class="form-control content-type-select" required
                            onchange="updateContentField('add', this.value)">
                            <option value="article">📰 Article</option>
                            <option value="tip">💡 Tip</option>
                            <option value="video">🎥 Video</option>
                            <option value="audio">🎧 Audio</option>
                            <option value="checklist">✅ Checklist</option>
                        </select>
                    </div>
                    <div class="col-md-3 mb-3 d-flex align-items-end">
                        <div class="form-check">
                            <input type="checkbox" name="is_featured" id="is_featured" class="form-check-input">
                            <label class="form-check-label" for="is_featured">
                                <i class="fas fa-star text-warning"></i> Featured Content
                            </label>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button name="add_content" class="btn btn-success">
                    <i class="fas fa-plus"></i> Add Content
                </button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times"></i> Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<?php include '../includes/admin_footer.php'; ?>

<script>
    function updateContentField(formId, contentType) {
        const contentFieldId = formId === 'add' ? 'content_field_add' : `content_field_${formId}`;
        const inputNameId = formId === 'add' ? 'content_text' : `content_text_${formId}`;
        const contentField = document.getElementById(contentFieldId);

        // Get existing value before replacing the field
        const existingInput = document.getElementById(inputNameId);
        const existingValue = existingInput ? existingInput.value : '';

        let fieldHTML = '';

        switch (contentType) {
            case 'video':
                fieldHTML = `
                <input type="url" class="form-control" name="content_text" id="${inputNameId}" required
                       value="${existingValue}"
                       placeholder="Enter YouTube URL, Vimeo URL, or video file path (e.g., assets/videos/video.mp4)">
                <div class="form-text">
                    <i class="fas fa-info-circle"></i> Supported: YouTube, Vimeo URLs, or local video files (.mp4, .webm, .ogg)
                </div>
            `;
                break;

            case 'audio':
                fieldHTML = `
                <input type="url" class="form-control" name="content_text" id="${inputNameId}" required
                       value="${existingValue}"
                       placeholder="Enter audio file URL or path (e.g., assets/audio/audio.mp3)">
                <div class="form-text">
                    <i class="fas fa-info-circle"></i> Supported: MP3, WAV, OGG audio files or streaming URLs
                </div>
            `;
                break;

            case 'checklist':
                fieldHTML = `
                <textarea class="form-control" name="content_text" id="${inputNameId}" rows="8" required
                          placeholder="Enter checklist items (one per line or separated by commas)&#10;Example:&#10;- Check blood pressure&#10;- Take prenatal vitamins&#10;- Schedule doctor appointment">${existingValue}</textarea>
                <div class="form-text">
                    <i class="fas fa-info-circle"></i> Enter checklist items, one per line or separated by commas
                </div>
            `;
                break;

            case 'tip':
                fieldHTML = `
                <textarea class="form-control" name="content_text" id="${inputNameId}" rows="4" required
                          placeholder="Enter a helpful tip or advice...">${existingValue}</textarea>
                <div class="form-text">
                    <i class="fas fa-info-circle"></i> Keep tips concise and actionable
                </div>
            `;
                break;

            default: // article
                fieldHTML = `
                <textarea class="form-control" name="content_text" id="${inputNameId}" rows="8" required
                          placeholder="Enter the full article content here...">${existingValue}</textarea>
                <div class="form-text">
                    <i class="fas fa-info-circle"></i> Enter the complete article text with proper formatting
                </div>
            `;
        }

        contentField.innerHTML = fieldHTML;
    }

    // Initialize content fields on page load
    document.addEventListener('DOMContentLoaded', function () {

        const addContentType = document.getElementById('content_type');
        if (addContentType) {
            updateContentField('add', addContentType.value);
        }


    });
</script>