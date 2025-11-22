<?php
session_start();

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "tabeya_system";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");

// Fetch approved reviews with customer names
$sql = "SELECT 
            cr.ReviewID,
            cr.CustomerID,
            CONCAT(c.FirstName, ' ', LEFT(c.LastName, 1), '.') AS DisplayName,
            cr.OverallRating,
            cr.FoodTasteRating,
            cr.PortionSizeRating,
            cr.CustomerServiceRating,
            cr.AmbienceRating,
            cr.CleanlinessRating,
            cr.FoodTasteComment,
            cr.PortionSizeComment,
            cr.CustomerServiceComment,
            cr.AmbienceComment,
            cr.CleanlinessComment,
            cr.GeneralComment,
            cr.CreatedDate
        FROM customer_reviews cr
        JOIN customers c ON cr.CustomerID = c.CustomerID
        WHERE cr.Status = 'Approved'
        ORDER BY cr.CreatedDate DESC";

$reviews_result = $conn->query($sql);

// Get statistics
$stats_sql = "SELECT 
    COUNT(*) as total,
    COALESCE(ROUND(AVG(OverallRating), 1), 0) as avg_rating,
    COALESCE(ROUND(AVG(FoodTasteRating), 1), 0) as avg_food,
    COALESCE(ROUND(AVG(PortionSizeRating), 1), 0) as avg_portion,
    COALESCE(ROUND(AVG(CustomerServiceRating), 1), 0) as avg_service,
    COALESCE(ROUND(AVG(AmbienceRating), 1), 0) as avg_ambience,
    COALESCE(ROUND(AVG(CleanlinessRating), 1), 0) as avg_cleanliness,
    SUM(CASE WHEN OverallRating = 5 THEN 1 ELSE 0 END) as five_star,
    SUM(CASE WHEN OverallRating = 4 THEN 1 ELSE 0 END) as four_star,
    SUM(CASE WHEN OverallRating = 3 THEN 1 ELSE 0 END) as three_star,
    SUM(CASE WHEN OverallRating = 2 THEN 1 ELSE 0 END) as two_star,
    SUM(CASE WHEN OverallRating = 1 THEN 1 ELSE 0 END) as one_star
FROM customer_reviews WHERE Status = 'Approved'";

$stats_result = $conn->query($stats_sql);
$stats = $stats_result->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Reviews - Tabeya</title>
    <link rel="stylesheet" href="CSS/ReviewDesign.css">
    <style>
        .review-form-section { margin-bottom: 20px; }
        .review-form-section h4 { color: #bc1823; margin-bottom: 10px; font-size: 14px; }
        .rating-category { display: flex; align-items: center; gap: 10px; margin-bottom: 15px; padding: 10px; background: #f9f9f9; border-radius: 8px; }
        .rating-category label { min-width: 120px; font-weight: 600; color: #333; font-size: 13px; }
        .category-stars { display: flex; gap: 5px; }
        .category-stars .star { font-size: 24px; color: #ddd; cursor: pointer; transition: color 0.2s; }
        .category-stars .star:hover, .category-stars .star.active { color: #FFD700; }
        .comment-field { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; font-size: 13px; resize: vertical; min-height: 60px; margin-top: 8px; }
        .overall-rating-input { text-align: center; padding: 15px; background: linear-gradient(135deg, #bc1823 0%, #e74c3c 100%); border-radius: 10px; margin-bottom: 20px; }
        .overall-rating-input h3 { color: white; margin-bottom: 10px; }
        .overall-rating-input .rating-stars { justify-content: center; }
        .overall-rating-input .rating-star { font-size: 36px; }
        .user-logged-in { background: #e8f5e9; padding: 10px 15px; border-radius: 8px; margin-bottom: 15px; display: flex; align-items: center; gap: 10px; }
        .user-logged-in .user-icon { font-size: 24px; }
        .user-logged-in .user-name { font-weight: 600; color: #2e7d32; }
        .login-required { background: #fff3e0; padding: 20px; border-radius: 8px; text-align: center; }
        .login-required a { color: #bc1823; font-weight: 600; }
        .review-detail-ratings { display: flex; flex-wrap: wrap; gap: 8px; margin: 10px 0; }
        .review-detail-ratings .rating-badge { background: #f5f5f5; padding: 4px 8px; border-radius: 12px; font-size: 11px; display: flex; align-items: center; gap: 4px; }
        .review-detail-ratings .rating-badge .stars { color: #FFD700; }
        .category-breakdown { margin-top: 20px; padding: 15px; background: #f9f9f9; border-radius: 8px; }
        .category-breakdown h4 { color: #bc1823; margin-bottom: 15px; }
        .category-bar { display: flex; align-items: center; margin-bottom: 10px; }
        .category-bar .cat-label { min-width: 120px; font-size: 12px; color: #666; }
        .category-bar .cat-bar { flex: 1; height: 8px; background: #e0e0e0; border-radius: 4px; overflow: hidden; margin: 0 10px; }
        .category-bar .cat-bar-fill { height: 100%; background: linear-gradient(90deg, #bc1823, #e74c3c); border-radius: 4px; }
        .category-bar .cat-value { min-width: 30px; font-size: 12px; font-weight: 600; color: #333; }
        .review-comments-accordion { margin-top: 10px; }
        .accordion-toggle { background: none; border: 1px solid #ddd; padding: 8px 12px; border-radius: 4px; cursor: pointer; font-size: 12px; color: #666; }
        .accordion-toggle:hover { background: #f5f5f5; }
        .accordion-content { display: none; margin-top: 10px; padding: 10px; background: #fafafa; border-radius: 8px; }
        .accordion-content.show { display: block; }
        .comment-item { margin-bottom: 8px; padding-bottom: 8px; border-bottom: 1px dashed #eee; }
        .comment-item:last-child { border-bottom: none; margin-bottom: 0; padding-bottom: 0; }
        .comment-item .comment-label { font-size: 11px; color: #bc1823; font-weight: 600; }
        .comment-item .comment-text { font-size: 13px; color: #444; margin-top: 2px; }
    </style>
</head>
<body>
    <header>
        <div class="logo">
            <img src="Photo/Tabeya Name.png" alt="Logo">
        </div>
        <nav>
            <a href="index.html">HOME</a>
            <a href="Menu.html">MENU</a>
            <a href="CaterReservation.html">CATER RESERVATION</a>
            <a href="GALLERY.html">GALLERY</a>
            <a href="Review.php" class="active">TESTIMONY</a>
            <a href="About.html">ABOUT</a>
            <a href="Login.html" id="account-link">PROFILE</a>
        </nav>
    </header>

    <section class="background">
        <img src="Photo/background.jpg" alt="Background">
        <div class="rectangle-panel">
            <div class="review-header">
                <h1>Customer Reviews</h1>
                <button id="write-review-btn" class="btn">Write a Review</button>
            </div>

            <div id="overall-rating-section" class="overall-rating">
                <h2 id="overall-rating-value"><?php echo $stats['avg_rating'] ?: '0.0'; ?></h2>
                <div class="star-rating" id="overall-star-rating">
                    <?php
                    $rating = floatval($stats['avg_rating']);
                    for ($i = 1; $i <= 5; $i++) {
                        if ($rating >= $i) {
                            echo '<span class="star active">★</span>';
                        } elseif ($rating > $i - 1) {
                            echo '<span class="star active half-star">★</span>';
                        } else {
                            echo '<span class="star">★</span>';
                        }
                    }
                    ?>
                </div>
                <div class="total-reviews">
                    Based on <span id="total-reviews-count"><?php echo $stats['total'] ?: '0'; ?></span> Reviews
                </div>
            </div>

            <div id="review-progress-section" class="review-progress">
                <?php
                $total = max(intval($stats['total']), 1);
                $stars = [5 => 'five', 4 => 'four', 3 => 'three', 2 => 'two', 1 => 'one'];
                foreach ($stars as $num => $name) {
                    $count = intval($stats[$name . '_star']) ?: 0;
                    $percent = ($count / $total) * 100;
                    echo '<div class="progress-row">
                            <div class="progress-label">' . $num . ' Stars</div>
                            <div class="progress-bar">
                                <div class="progress-bar-fill" style="width: ' . $percent . '%"></div>
                            </div>
                          </div>';
                }
                ?>
            </div>

            <div class="category-breakdown">
                <h4>Category Ratings</h4>
                <?php
                $categories = [
                    'Food Taste' => $stats['avg_food'],
                    'Portion Size' => $stats['avg_portion'],
                    'Service' => $stats['avg_service'],
                    'Ambience' => $stats['avg_ambience'],
                    'Cleanliness' => $stats['avg_cleanliness']
                ];
                foreach ($categories as $label => $value) {
                    $val = floatval($value) ?: 0;
                    $percent = ($val / 5) * 100;
                    echo '<div class="category-bar">
                            <span class="cat-label">' . $label . '</span>
                            <div class="cat-bar"><div class="cat-bar-fill" style="width: ' . $percent . '%"></div></div>
                            <span class="cat-value">' . number_format($val, 1) . '</span>
                          </div>';
                }
                ?>
            </div>

            <div id="reviews-container" class="user-reviews">
                <?php 
                if ($reviews_result && $reviews_result->num_rows > 0) {
                    while ($review = $reviews_result->fetch_assoc()) {
                        echo '<div class="user-review">';
                        echo '<div class="review-header">';
                        echo '<h3>' . htmlspecialchars($review['DisplayName']) . '</h3>';
                        echo '<div class="review-rating">';
                        for ($i = 1; $i <= 5; $i++) {
                            $active = ($i <= $review['OverallRating']) ? 'active' : '';
                            echo '<span class="star ' . $active . '">★</span>';
                        }
                        echo '</div></div>';
                        
                        echo '<div class="review-detail-ratings">';
                        $cats = [
                            '🍽️ Food' => $review['FoodTasteRating'],
                            '📏 Portion' => $review['PortionSizeRating'],
                            '👨‍💼 Service' => $review['CustomerServiceRating'],
                            '✨ Ambience' => $review['AmbienceRating'],
                            '🧹 Clean' => $review['CleanlinessRating']
                        ];
                        foreach ($cats as $icon => $rating) {
                            if ($rating) {
                                echo '<span class="rating-badge">' . $icon . ' <span class="stars">' . str_repeat('★', $rating) . '</span></span>';
                            }
                        }
                        echo '</div>';
                        
                        if ($review['GeneralComment']) {
                            echo '<p>' . htmlspecialchars($review['GeneralComment']) . '</p>';
                        }
                        
                        $hasComments = $review['FoodTasteComment'] || $review['PortionSizeComment'] || 
                                      $review['CustomerServiceComment'] || $review['AmbienceComment'] || 
                                      $review['CleanlinessComment'];
                        
                        if ($hasComments) {
                            echo '<div class="review-comments-accordion">';
                            echo '<button class="accordion-toggle" onclick="toggleAccordion(this)">View detailed feedback ▼</button>';
                            echo '<div class="accordion-content">';
                            
                            $comments = [
                                'Food Taste' => $review['FoodTasteComment'],
                                'Portion Size' => $review['PortionSizeComment'],
                                'Customer Service' => $review['CustomerServiceComment'],
                                'Ambience' => $review['AmbienceComment'],
                                'Cleanliness' => $review['CleanlinessComment']
                            ];
                            
                            foreach ($comments as $label => $comment) {
                                if ($comment) {
                                    echo '<div class="comment-item">';
                                    echo '<div class="comment-label">' . $label . '</div>';
                                    echo '<div class="comment-text">' . htmlspecialchars($comment) . '</div>';
                                    echo '</div>';
                                }
                            }
                            echo '</div></div>';
                        }
                        
                        echo '<small style="color: #999; font-size: 11px;">' . date('M d, Y', strtotime($review['CreatedDate'])) . '</small>';
                        echo '</div>';
                    }
                } else {
                    echo '<p style="text-align: center; color: #666;">No reviews yet. Be the first to share your experience!</p>';
                }
                ?>
            </div>
        </div>
    </section>

    <div id="review-modal" class="review-modal">
        <div class="review-modal-content" style="max-width: 600px; max-height: 90vh; overflow-y: auto;">
            <h2>Share Your Experience</h2>
            
            <div id="user-info-section"></div>
            
            <div class="overall-rating-input">
                <h3>Overall Rating</h3>
                <div class="rating-stars" id="overall-stars">
                    <span class="rating-star" data-rating="1">★</span>
                    <span class="rating-star" data-rating="2">★</span>
                    <span class="rating-star" data-rating="3">★</span>
                    <span class="rating-star" data-rating="4">★</span>
                    <span class="rating-star" data-rating="5">★</span>
                </div>
            </div>

            <div class="review-form-section">
                <h4>Rate Each Category</h4>
                
                <div class="rating-category">
                    <label>🍽️ Food Taste & Quality</label>
                    <div class="category-stars" data-category="food">
                        <span class="star" data-rating="1">★</span>
                        <span class="star" data-rating="2">★</span>
                        <span class="star" data-rating="3">★</span>
                        <span class="star" data-rating="4">★</span>
                        <span class="star" data-rating="5">★</span>
                    </div>
                </div>
                <textarea class="comment-field" id="food-comment" placeholder="Tell us about the food..."></textarea>

                <div class="rating-category">
                    <label>📏 Portion Size</label>
                    <div class="category-stars" data-category="portion">
                        <span class="star" data-rating="1">★</span>
                        <span class="star" data-rating="2">★</span>
                        <span class="star" data-rating="3">★</span>
                        <span class="star" data-rating="4">★</span>
                        <span class="star" data-rating="5">★</span>
                    </div>
                </div>
                <textarea class="comment-field" id="portion-comment" placeholder="Was the portion satisfying?"></textarea>

                <div class="rating-category">
                    <label>👨‍💼 Customer Service</label>
                    <div class="category-stars" data-category="service">
                        <span class="star" data-rating="1">★</span>
                        <span class="star" data-rating="2">★</span>
                        <span class="star" data-rating="3">★</span>
                        <span class="star" data-rating="4">★</span>
                        <span class="star" data-rating="5">★</span>
                    </div>
                </div>
                <textarea class="comment-field" id="service-comment" placeholder="How was the service?"></textarea>

                <div class="rating-category">
                    <label>✨ Ambience</label>
                    <div class="category-stars" data-category="ambience">
                        <span class="star" data-rating="1">★</span>
                        <span class="star" data-rating="2">★</span>
                        <span class="star" data-rating="3">★</span>
                        <span class="star" data-rating="4">★</span>
                        <span class="star" data-rating="5">★</span>
                    </div>
                </div>
                <textarea class="comment-field" id="ambience-comment" placeholder="Describe the atmosphere..."></textarea>

                <div class="rating-category">
                    <label>🧹 Cleanliness</label>
                    <div class="category-stars" data-category="cleanliness">
                        <span class="star" data-rating="1">★</span>
                        <span class="star" data-rating="2">★</span>
                        <span class="star" data-rating="3">★</span>
                        <span class="star" data-rating="4">★</span>
                        <span class="star" data-rating="5">★</span>
                    </div>
                </div>
                <textarea class="comment-field" id="cleanliness-comment" placeholder="How clean was the place?"></textarea>
            </div>

            <div class="review-form-section">
                <h4>General Comments (Optional)</h4>
                <textarea class="comment-field" id="general-comment" placeholder="Share your overall experience..." style="min-height: 80px;"></textarea>
            </div>

            <div style="display: flex; gap: 10px; margin-top: 20px;">
                <button id="submit-review-btn" class="submit-review-btn" style="flex: 1;">Submit Review</button>
                <button id="close-modal-btn" style="flex: 0 0 100px;">Cancel</button>
            </div>
        </div>
    </div>

    <footer>
        <div class="contact-section">
            <div class="container">
                <div class="contact-info">
                    <h2>Contact Us</h2>
                    <p>Have any questions? We'd love to hear from you.</p>
                    <div class="info-group">
                        <div class="info-item visit-us">
                            <img src="Photo/VisitUs.png" alt="Location" class="icon">
                            <div><strong>Visit us</strong><p>Poblacion 2, Vinzons Avenue,<br>Vinzons, Camarines Norte</p></div>
                        </div>
                        <div class="info-item call-us">
                            <img src="Photo/Selpon.png" alt="Phone" class="icon">
                            <div><strong>Call us</strong><p>09380839641</p></div>
                        </div>
                        <div class="info-item connect-us">
                            <img src="Photo/Facebook.png" alt="Facebook" class="icon">
                            <div><strong>Connect to us</strong><a href="https://www.facebook.com/profile.php?id=100063540027038" target="_blank" class="no-underline">Tabeya, VCN</a></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </footer>

    <script>
    const USER_KEY = 'currentUser';
    let currentUser = null;
    let ratings = { overall: 0, food: 0, portion: 0, service: 0, ambience: 0, cleanliness: 0 };

    function getCurrentUser() {
        try { return JSON.parse(localStorage.getItem(USER_KEY)); } 
        catch (e) { return null; }
    }

    function updateAccountLink() {
        const link = document.getElementById('account-link');
        const user = getCurrentUser();
        if (user && link) {
            link.textContent = user.firstName ? user.firstName.toUpperCase() : 'PROFILE';
            link.href = 'Profile.html';
        }
    }

    function toggleAccordion(btn) {
        const content = btn.nextElementSibling;
        content.classList.toggle('show');
        btn.textContent = content.classList.contains('show') ? 'Hide detailed feedback ▲' : 'View detailed feedback ▼';
    }

    document.addEventListener('DOMContentLoaded', () => {
        currentUser = getCurrentUser();
        updateAccountLink();
        setupRatingStars();
        setupModalHandlers();
    });

    function setupRatingStars() {
        // Overall rating
        document.querySelectorAll('#overall-stars .rating-star').forEach(star => {
            star.addEventListener('click', function() {
                ratings.overall = parseInt(this.dataset.rating);
                updateStars('#overall-stars .rating-star', ratings.overall);
            });
        });

        // Category ratings
        document.querySelectorAll('.category-stars').forEach(container => {
            const category = container.dataset.category;
            container.querySelectorAll('.star').forEach(star => {
                star.addEventListener('click', function() {
                    ratings[category] = parseInt(this.dataset.rating);
                    updateStars(`.category-stars[data-category="${category}"] .star`, ratings[category]);
                });
            });
        });
    }

    function updateStars(selector, rating) {
        document.querySelectorAll(selector).forEach((star, idx) => {
            star.classList.toggle('active', idx < rating);
        });
    }

    function setupModalHandlers() {
        const modal = document.getElementById('review-modal');
        const writeBtn = document.getElementById('write-review-btn');
        const closeBtn = document.getElementById('close-modal-btn');
        const submitBtn = document.getElementById('submit-review-btn');
        const userSection = document.getElementById('user-info-section');

        writeBtn.addEventListener('click', () => {
            currentUser = getCurrentUser();
            if (!currentUser) {
                userSection.innerHTML = `<div class="login-required">
                    <p>Please <a href="Login.html">log in</a> to write a review.</p>
                </div>`;
                submitBtn.disabled = true;
            } else {
                userSection.innerHTML = `<div class="user-logged-in">
                    <span class="user-icon">👤</span>
                    <span>Reviewing as: <span class="user-name">${currentUser.firstName} ${currentUser.lastName}</span></span>
                </div>`;
                submitBtn.disabled = false;
            }
            modal.style.display = 'block';
        });

        closeBtn.addEventListener('click', () => {
            modal.style.display = 'none';
            resetForm();
        });

        window.addEventListener('click', (e) => {
            if (e.target === modal) {
                modal.style.display = 'none';
                resetForm();
            }
        });

        submitBtn.addEventListener('click', submitReview);
    }

    function resetForm() {
        ratings = { overall: 0, food: 0, portion: 0, service: 0, ambience: 0, cleanliness: 0 };
        document.querySelectorAll('.rating-star, .category-stars .star').forEach(s => s.classList.remove('active'));
        document.querySelectorAll('.comment-field').forEach(f => f.value = '');
    }

    async function submitReview() {
        if (!currentUser) {
            alert('Please log in to submit a review.');
            return;
        }

        if (ratings.overall === 0) {
            alert('Please provide an overall rating.');
            return;
        }

        const data = {
            customerId: currentUser.customerId,
            overallRating: ratings.overall,
            foodRating: ratings.food,
            portionRating: ratings.portion,
            serviceRating: ratings.service,
            ambienceRating: ratings.ambience,
            cleanlinessRating: ratings.cleanliness,
            foodComment: document.getElementById('food-comment').value.trim(),
            portionComment: document.getElementById('portion-comment').value.trim(),
            serviceComment: document.getElementById('service-comment').value.trim(),
            ambienceComment: document.getElementById('ambience-comment').value.trim(),
            cleanlinessComment: document.getElementById('cleanliness-comment').value.trim(),
            generalComment: document.getElementById('general-comment').value.trim()
        };

        try {
            const response = await fetch('submit_customer_review.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });

            const result = await response.json();

            if (result.success) {
                alert('Thank you! Your review has been submitted and is pending approval.');
                document.getElementById('review-modal').style.display = 'none';
                resetForm();
            } else {
                alert('Error: ' + result.message);
            }
        } catch (error) {
            console.error('Submit error:', error);
            alert('An error occurred. Please try again.');
        }
    }
    </script>
</body>
</html>
<?php $conn->close(); ?>