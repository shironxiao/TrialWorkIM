// ============================================================
// ENHANCED REVIEW SYSTEM JAVASCRIPT
// Supports Order/Reservation/General reviews with anonymous option
// ============================================================

const USER_KEY = 'currentUser';
const CART_KEY = 'tabeyaCart';
const LOGIN_PAGE = 'Login.html';
const PROFILE_PAGE = 'Profile.html';
const CHECKOUT_PAGE = 'Checkout.html';
const REVIEW_PAGE = 'Review.php';

let currentUser = null;
let ratings = { overall: 0, food: 0, portion: 0, service: 0, ambience: 0, cleanliness: 0 };
let cart = JSON.parse(localStorage.getItem(CART_KEY)) || [];
let selectedFeedbackType = 'General';
let selectedOrderId = null;
let selectedReservationId = null;
let reviewableOrders = [];
let reviewableReservations = [];
let reviewsVisible = 3; // Initial number of reviews to show

function getCurrentUser() {
    try {
        return JSON.parse(localStorage.getItem(USER_KEY));
    } catch (e) {
        return null;
    }
}

function updateAccountLink() {
    const link = document.getElementById('account-link');
    const user = getCurrentUser();
    if (!link) return;

    if (user) {
        const userName = user.name ? user.name.split(' ')[0].toUpperCase() : (user.firstName || 'PROFILE').toUpperCase();
        link.textContent = userName;
        link.href = PROFILE_PAGE;
    } else {
        link.textContent = 'PROFILE';
        link.href = LOGIN_PAGE;
    }
}

// ✅ FIXED: Make toggleAccordion globally accessible
window.toggleAccordion = function (btn) {
    const content = btn.nextElementSibling;
    const isShowing = content.classList.contains('show');

    if (isShowing) {
        content.classList.remove('show');
        btn.innerHTML = 'View detailed feedback ⌵';
    } else {
        content.classList.add('show');
        btn.innerHTML = 'Hide detailed feedback ⌃';
    }
};

document.addEventListener('DOMContentLoaded', () => {
    currentUser = getCurrentUser();
    updateAccountLink();
    updateCartDisplay();
    setupRatingStars();
    setupModalHandlers();
    setupFeedbackTypeButtons();
    setupSorting();
    setupLoadMore();
});

function setupSorting() {
    const sortSelect = document.querySelector('.sort-select');
    if (!sortSelect) return;

    sortSelect.addEventListener('change', function () {
        const value = this.value;
        const container = document.getElementById('reviews-container');
        const cards = Array.from(container.querySelectorAll('.review-card'));

        cards.sort((a, b) => {
            const dateA = new Date(a.dataset.date);
            const dateB = new Date(b.dataset.date);

            if (value === 'recent') {
                return dateB - dateA;
            } else {
                return dateA - dateB;
            }
        });

        // Clear and re-append in new order
        container.innerHTML = '';
        cards.forEach(card => container.appendChild(card));
        updateReviewVisibility();
    });
}

function setupLoadMore() {
    const loadMoreBtn = document.querySelector('.load-more-btn');
    if (!loadMoreBtn) return;

    loadMoreBtn.addEventListener('click', () => {
        const cards = document.querySelectorAll('.review-card');

        if (reviewsVisible >= cards.length) {
            // If already showing all, reset to initial 3
            reviewsVisible = 3;
        } else {
            // Otherwise, load 3 more
            reviewsVisible += 3;
        }

        updateReviewVisibility();

        // If showing less, scroll back to reviews top
        if (reviewsVisible === 3) {
            const container = document.getElementById('reviews-container');
            if (container) {
                container.scrollIntoView({ behavior: 'smooth' });
            }
        }
    });

    updateReviewVisibility();
}

function updateReviewVisibility() {
    const cards = document.querySelectorAll('.review-card');
    const loadMoreContainer = document.querySelector('.load-more');

    if (cards.length === 0) {
        if (loadMoreContainer) loadMoreContainer.style.display = 'none';
        return;
    }

    cards.forEach((card, index) => {
        if (index < reviewsVisible) {
            card.style.display = 'block';
            // Optional: add a small fade-in effect
            setTimeout(() => {
                card.style.opacity = '1';
                card.style.transform = 'translateY(0)';
            }, 10);
        } else {
            card.style.display = 'none';
        }
    });

    if (loadMoreContainer) {
        const loadMoreBtn = loadMoreContainer.querySelector('.load-more-btn');

        if (cards.length <= 3) {
            // Hide if 3 or fewer total
            loadMoreContainer.style.display = 'none';
        } else {
            loadMoreContainer.style.display = 'block';
            if (reviewsVisible >= cards.length) {
                loadMoreBtn.textContent = 'Show less reviews';
            } else {
                loadMoreBtn.textContent = 'Load more reviews';
            }
        }
    }
}

function setupFeedbackTypeButtons() {
    const buttons = document.querySelectorAll('.feedback-type-btn');

    buttons.forEach(btn => {
        btn.addEventListener('click', async function () {
            // Remove active class from all buttons
            buttons.forEach(b => b.classList.remove('active'));
            this.classList.add('active');

            selectedFeedbackType = this.dataset.type;
            selectedOrderId = null;
            selectedReservationId = null;

            // Hide all sections first
            document.getElementById('orders-section').classList.remove('show');
            document.getElementById('reservations-section').classList.remove('show');

            // Show relevant section
            if (selectedFeedbackType === 'Order') {
                await loadReviewableOrders();
                document.getElementById('orders-section').classList.add('show');
            } else if (selectedFeedbackType === 'Reservation') {
                await loadReviewableReservations();
                document.getElementById('reservations-section').classList.add('show');
            }
        });
    });
}

async function loadReviewableOrders() {
    const ordersList = document.getElementById('orders-list');
    ordersList.innerHTML = '<p style="text-align:center; color:#999;">Loading orders...</p>';

    try {
        const response = await fetch(`fetch_reviewable_items.php?customer_id=${currentUser.customerId}`);
        const data = await response.json();

        if (!data.success) {
            throw new Error(data.message);
        }

        reviewableOrders = data.orders;

        if (reviewableOrders.length === 0) {
            ordersList.innerHTML = '<p style="text-align:center; color:#999;">No recent orders found.</p>';
            return;
        }

        ordersList.innerHTML = '';
        reviewableOrders.forEach(order => {
            const div = document.createElement('div');
            div.className = 'reviewable-item' + (order.hasReview ? ' reviewed' : '');
            div.dataset.orderId = order.id;

            div.innerHTML = `
                <div class="item-header">
                    <strong>Order</strong>
                    <span class="item-badge ${order.hasReview ? 'reviewed' : ''}">${order.hasReview ? 'Reviewed' : 'Not Reviewed'}</span>
                </div>
                <div>Date: ${formatDate(order.date)}</div>
                <div>Total: ₱${parseFloat(order.total).toFixed(2)}</div>
                <div class="item-products">Items: ${order.items}</div>
            `;

            if (!order.hasReview) {
                div.addEventListener('click', function () {
                    selectOrder(order.id);
                });
            }

            ordersList.appendChild(div);
        });

    } catch (error) {
        console.error('Error loading orders:', error);
        ordersList.innerHTML = '<p style="text-align:center; color:red;">Failed to load orders.</p>';
    }
}

async function loadReviewableReservations() {
    const reservationsList = document.getElementById('reservations-list');
    reservationsList.innerHTML = '<p style="text-align:center; color:#999;">Loading reservations...</p>';

    try {
        const response = await fetch(`fetch_reviewable_items.php?customer_id=${currentUser.customerId}`);
        const data = await response.json();

        if (!data.success) {
            throw new Error(data.message);
        }

        reviewableReservations = data.reservations;

        if (reviewableReservations.length === 0) {
            reservationsList.innerHTML = '<p style="text-align:center; color:#999;">No recent reservations found.</p>';
            return;
        }

        reservationsList.innerHTML = '';
        reviewableReservations.forEach(reservation => {
            const div = document.createElement('div');
            div.className = 'reviewable-item' + (reservation.hasReview ? ' reviewed' : '');
            div.dataset.reservationId = reservation.id;

            div.innerHTML = `
                <div class="item-header">
                    <strong>Reservation</strong>
                    <span class="item-badge ${reservation.hasReview ? 'reviewed' : ''}">${reservation.hasReview ? 'Reviewed' : 'Not Reviewed'}</span>
                </div>
                <div>Date: ${formatDate(reservation.date)}</div>
                <div>Event: ${capitalizeFirst(reservation.eventType)} - ${reservation.guests} guests</div>
                <div>Total: ₱${parseFloat(reservation.total).toFixed(2)}</div>
                <div class="item-products">Items: ${reservation.items}</div>
            `;

            if (!reservation.hasReview) {
                div.addEventListener('click', function () {
                    selectReservation(reservation.id);
                });
            }

            reservationsList.appendChild(div);
        });

    } catch (error) {
        console.error('Error loading reservations:', error);
        reservationsList.innerHTML = '<p style="text-align:center; color:red;">Failed to load reservations.</p>';
    }
}

function selectOrder(orderId) {
    // Remove selection from all orders
    document.querySelectorAll('#orders-list .reviewable-item').forEach(item => {
        item.classList.remove('selected');
    });

    // Select this order
    const selectedItem = document.querySelector(`#orders-list .reviewable-item[data-order-id="${orderId}"]`);
    if (selectedItem) {
        selectedItem.classList.add('selected');
        selectedOrderId = orderId;
        selectedReservationId = null;
    }
}

function selectReservation(reservationId) {
    // Remove selection from all reservations
    document.querySelectorAll('#reservations-list .reviewable-item').forEach(item => {
        item.classList.remove('selected');
    });

    // Select this reservation
    const selectedItem = document.querySelector(`#reservations-list .reviewable-item[data-reservation-id="${reservationId}"]`);
    if (selectedItem) {
        selectedItem.classList.add('selected');
        selectedReservationId = reservationId;
        selectedOrderId = null;
    }
}

function formatDate(dateStr) {
    try {
        const date = new Date(dateStr);
        if (isNaN(date.getTime())) {
            return dateStr;
        }
        const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        return `${months[date.getMonth()]} ${date.getDate()}, ${date.getFullYear()}`;
    } catch (error) {
        console.error('Date formatting error:', error);
        return dateStr;
    }
}

function capitalizeFirst(str) {
    return str.charAt(0).toUpperCase() + str.slice(1);
}

// ✅ FIXED: Proper star rating setup with automatic Overall calculation
function setupRatingStars() {
    // Overall rating is now read-only and system-generated
    const overallStars = document.querySelectorAll('#overall-stars .rating-star');
    overallStars.forEach(star => {
        star.style.cursor = 'default';
        star.classList.add('read-only');
    });

    // Category ratings
    document.querySelectorAll('.category-stars').forEach(container => {
        const category = container.dataset.category;
        container.querySelectorAll('.star').forEach(star => {
            star.addEventListener('click', function () {
                const rating = parseInt(this.dataset.rating);
                ratings[category] = rating;

                // Update stars in this category
                const categoryStars = container.querySelectorAll('.star');
                categoryStars.forEach((s, idx) => {
                    if (idx < rating) {
                        s.classList.add('active');
                    } else {
                        s.classList.remove('active');
                    }
                });

                // Automatically calculate overall rating
                calculateOverallRating();
            });
        });
    });
}

function calculateOverallRating() {
    const categories = ['food', 'portion', 'service', 'ambience', 'cleanliness'];
    let total = 0;
    let counted = 0;

    categories.forEach(cat => {
        if (ratings[cat] > 0) {
            total += ratings[cat];
            counted++;
        }
    });

    if (counted > 0) {
        const avg = total / counted;
        ratings.overall = Math.round(avg);

        // Update the visual stars for Overall Rating
        updateStars('#overall-stars .rating-star', ratings.overall);

        // Update the numerical display
        const scoreDisplay = document.getElementById('overall-rating-value-modal');
        if (scoreDisplay) {
            scoreDisplay.textContent = avg.toFixed(1);
        }
    } else {
        ratings.overall = 0;
        updateStars('#overall-stars .rating-star', 0);
        const scoreDisplay = document.getElementById('overall-rating-value-modal');
        if (scoreDisplay) {
            scoreDisplay.textContent = '0.0';
        }
    }
}

function updateStars(selector, rating) {
    document.querySelectorAll(selector).forEach((star, idx) => {
        if (idx < rating) {
            star.classList.add('active');
        } else {
            star.classList.remove('active');
        }
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

    submitBtn.addEventListener('click', submitFeedback);
}

function resetForm() {
    ratings = { overall: 0, food: 0, portion: 0, service: 0, ambience: 0, cleanliness: 0 };
    document.querySelectorAll('.rating-star, .category-stars .star').forEach(s => s.classList.remove('active'));
    document.querySelectorAll('.comment-field').forEach(f => f.value = '');
    document.getElementById('anonymous-checkbox').checked = false;

    // Reset numerical display
    const scoreDisplay = document.getElementById('overall-rating-value-modal');
    if (scoreDisplay) {
        scoreDisplay.textContent = '0.0';
    }

    // Reset feedback type to General
    selectedFeedbackType = 'General';
    selectedOrderId = null;
    selectedReservationId = null;

    document.querySelectorAll('.feedback-type-btn').forEach(btn => {
        btn.classList.remove('active');
        if (btn.dataset.type === 'General') {
            btn.classList.add('active');
        }
    });

    document.getElementById('orders-section').classList.remove('show');
    document.getElementById('reservations-section').classList.remove('show');
}

async function submitFeedback() {
    if (!currentUser) {
        alert('Please log in to submit a review.');
        return;
    }

    if (ratings.overall === 0) {
        alert('Please provide an overall rating.');
        return;
    }

    // Validate feedback type selection
    if (selectedFeedbackType === 'Order' && !selectedOrderId) {
        alert('Please select an order to review, or choose "General Experience".');
        return;
    }

    if (selectedFeedbackType === 'Reservation' && !selectedReservationId) {
        alert('Please select a reservation to review, or choose "General Experience".');
        return;
    }

    const isAnonymous = document.getElementById('anonymous-checkbox').checked;

    const data = {
        customerId: currentUser.customerId,
        feedbackType: selectedFeedbackType,
        orderId: selectedOrderId,
        reservationId: selectedReservationId,
        overallRating: ratings.overall,
        foodRating: ratings.food || 0,
        portionRating: ratings.portion || 0,
        serviceRating: ratings.service || 0,
        ambienceRating: ratings.ambience || 0,
        cleanlinessRating: ratings.cleanliness || 0,
        foodComment: document.getElementById('food-comment')?.value.trim() || '',
        portionComment: document.getElementById('portion-comment')?.value.trim() || '',
        serviceComment: document.getElementById('service-comment')?.value.trim() || '',
        ambienceComment: document.getElementById('ambience-comment')?.value.trim() || '',
        cleanlinessComment: document.getElementById('cleanliness-comment')?.value.trim() || '',
        reviewMessage: document.getElementById('review-message')?.value.trim() || '',
        isAnonymous: isAnonymous
    };

    console.log('Submitting feedback data:', data);

    try {
        const response = await fetch('submit_customer_feedback.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify(data)
        });

        const text = await response.text();
        console.log('Raw response:', text);

        let result;
        try {
            result = JSON.parse(text);
        } catch (parseError) {
            console.error('JSON parse error:', parseError);
            throw new Error('Server returned invalid JSON: ' + text.substring(0, 200));
        }

        if (result.success) {
            alert('✅ ' + result.message);
            document.getElementById('review-modal').style.display = 'none';
            resetForm();
            // Reload to show updated stats (or use AJAX to update)
            setTimeout(() => window.location.reload(), 1000);
        } else {
            alert('❌ Error: ' + result.message);
        }

    } catch (error) {
        console.error('Submit error:', error);
        alert('An error occurred while submitting your review. Please try again.\n\nError: ' + error.message);
    }
}

function ensureUserLoggedIn(redirectTarget = REVIEW_PAGE) {
    if (!getCurrentUser()) {
        alert("You must log in to perform this action.");
        localStorage.setItem('redirectAfterLogin', redirectTarget);
        window.location.href = LOGIN_PAGE;
        return false;
    }
    return true;
}

function updateCartDisplay() {
    const cartCountElement = document.getElementById('cart-item-count');
    if (!cartCountElement) return;
    const totalItems = cart.reduce((sum, item) => sum + item.quantity, 0);
    cartCountElement.textContent = totalItems;
}

// Cart modal logic removed (renderCartModal, saveCart, item quantity updates, setupCartListeners)
// Handled by Cart.html now.

function saveCart() {
    localStorage.setItem(CART_KEY, JSON.stringify(cart));
    updateCartDisplay();
}