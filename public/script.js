// Test packages data
const plans = [
  { 
    id: "2hrs", 
    name: "2 Hours", 
    price: 10, 
    duration: "2h",
    popular: false
  },
  { 
    id: "6hrs", 
    name: "6 Hours", 
    price: 30, 
    duration: "6h",
    popular: false
  },
  { 
    id: "12hrs", 
    name: "12 Hours", 
    price: 50, 
    duration: "12h",
    popular: true
  },
  { 
    id: "24hrs", 
    name: "24 Hours", 
    price: 80, 
    duration: "24h",
    popular: false
  },
  { 
    id: "1week", 
    name: "1 Week", 
    price: 300, 
    duration: "7d",
    full: true,
    popular: false
  }
];

// DOM Elements (will be queried after DOM is ready)
let plansContainer;
let voucherInput;
let applyVoucherBtn;
let voucherMessage;
let themeButtons;

// Phone number normalization helper (frontend)
function normalizePhone(phone) {
  // Remove all non-digits and leading +
  phone = phone.replace(/\D/g, '');
  
  // If 10 digits and starts with 0, convert 0 to 254 (e.g., 0712345678 => 254712345678)
  if (phone.length === 10 && phone[0] === '0') {
    return '254' + phone.slice(1);
  }
  
  // If 9 digits and starts with 7, prepend 254 (e.g., 712345678 => 254712345678)
  if (phone.length === 9 && phone[0] === '7') {
    return '254' + phone;
  }
  
  // If already 12 digits and starts with 254, return as is
  if (phone.length === 12 && phone.startsWith('254')) {
    return phone;
  }
  
  // Invalid format
  return null;
}

// Initialize the page
function initHotspotUI() {
  // Query DOM elements after DOMContentLoaded to avoid null selectors
  plansContainer = document.getElementById("planList");
  voucherInput = document.getElementById("voucherCode");
  applyVoucherBtn = document.getElementById("redeemVoucher");
  voucherMessage = document.getElementById("voucherNotice");
  themeButtons = document.querySelectorAll(".theme-switch");

  if (!plansContainer) {
    console.error('plansContainer not found: #planList is missing in the markup');
    return;
  }

  renderPlans();
  setupEventListeners();
  setupThemeSwitcher();
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initHotspotUI);
} else {
  // DOM already ready
  initHotspotUI();
}

// Render internet plans
function renderPlans() {
  plansContainer.innerHTML = "";

  plans.forEach(plan => {
    const card = document.createElement('div');
    card.className = 'plan-card';
    card.innerHTML = `
      <div>
        <h3>${plan.name}</h3>
        <div class="plan-charge">KES ${plan.price}</div>
        <div class="muted">${plan.duration}</div>
      </div>
      <div style="margin-top:8px">
        <button class="plan-cta" data-id="${plan.id}">Get</button>
      </div>
    `;
    plansContainer.appendChild(card);
  });

  // attach click handlers
  document.querySelectorAll('.plan-cta').forEach(btn => {
    btn.addEventListener('click', (e) => {
      handlePlanClick(e.currentTarget.getAttribute('data-id'));
    });
  });
}

// Handle plan selection
function handlePlanClick(planId) {
  const plan = plans.find(p => p.id === planId);
  const phone = prompt(`Phone for ${plan.name} (KES ${plan.price}) - e.g. 0712345678 or 254712345678`);
  
  if (!phone) return;
  
  // Validate phone number (flexible format: 07XXXXXXXX, 254712345678, +254712345678)
  const normalized = normalizePhone(phone.trim());
  if (!normalized) {
    showVoucherMessage("Please enter a valid phone number (e.g., 0712345678 or 254712345678)", true);
    return;
  }
  
  // Show loading state
  showVoucherMessage(`Processing payment for ${plan.name}...`, false);
  
  // Call backend API
  const formData = new FormData();
  formData.append('phone', normalized);
  formData.append('plan', planId);
  
  fetch('/hotspot/api/pay.php', {
    method: 'POST',
    body: formData
  })
  .then(response => response.json())
  .then(data => {
      if (data.status === 'pending') {
        showVoucherMessage(`STK pushed — finish payment on phone.`, false);
        // Poll for confirmation
        pollPaymentConfirmation(data.checkoutRequestID);
      } else if (data.status === 'error') {
        showVoucherMessage(`Error: ${data.msg || 'Payment failed'}`, true);
      } else {
        showVoucherMessage(`Payment received — voucher: <strong>${data.voucher}</strong>`, false);
        voucherInput.value = data.voucher;
      }
  })
  .catch(error => {
    console.error('Payment error:', error);
    showVoucherMessage('Network error. Please try again.', true);
  });
}

// Poll for payment confirmation
function pollPaymentConfirmation(checkoutRequestID, attempts = 0) {
  if (attempts > 18) {
    showVoucherMessage('Payment confirmation timed out. Check your phone or try again.', true);
    return;
  }
  
  setTimeout(() => {
      const formData = new FormData();
      formData.append('checkoutRequestID', checkoutRequestID);
    
    fetch('/hotspot/api/confirm.php', {
      method: 'POST',
      body: formData
    })
    .then(response => response.json())
    .then(data => {
      if (data.status === 'success') {
        showVoucherMessage(`Payment confirmed! Voucher: <strong>${data.voucher}</strong>`, false);
        voucherInput.value = data.voucher;
      } else if (data.status === 'pending') {
        pollPaymentConfirmation(checkoutRequestID, attempts + 1);
      } else {
        showVoucherMessage('Payment failed. Please try again.', true);
      }
    })
    .catch(error => {
      console.error('Confirmation error:', error);
      pollPaymentConfirmation(checkoutRequestID, attempts + 1);
    });
  }, 5000);
}

// Handle voucher application
function applyVoucher() {
  const voucher = voucherInput.value.trim();
  
  if (!voucher) {
    showVoucherMessage("Please enter a voucher code", true);
    return;
  }
  
  // Show loading state
  applyVoucherBtn.disabled = true;
  applyVoucherBtn.innerHTML = '...';
  
  // Call backend API
  const formData = new FormData();
  formData.append('voucher', voucher);
  
  fetch('/hotspot/api/voucher.php', {
    method: 'POST',
    body: formData
  })
  .then(response => response.json())
  .then(data => {
      if (data.status === 'ok') {
        showVoucherMessage(`Activated — username: <strong>${data.username}</strong>`, false);
        setTimeout(() => { voucherInput.value = ''; }, 1500);
      } else {
        showVoucherMessage(`Error: ${data.msg || 'Invalid voucher'}`, true);
      }
    
    applyVoucherBtn.disabled = false;
    applyVoucherBtn.innerHTML = 'Apply';
  })
  .catch(error => {
    console.error('Voucher error:', error);
    showVoucherMessage('Network error. Please try again.', true);
    applyVoucherBtn.disabled = false;
    applyVoucherBtn.innerHTML = 'Apply';
  });
}

// Show voucher message
function showVoucherMessage(message, isError) {
  voucherMessage.innerHTML = message;
  voucherMessage.style.color = isError ? 'var(--alert)' : 'var(--accent-2)';
}

// Theme switching
function setupThemeSwitcher() {
  themeButtons.forEach(button => {
    button.addEventListener('click', () => {
      const theme = button.getAttribute('data-theme');
      document.documentElement.setAttribute('data-theme', theme);
      localStorage.setItem('hotspot-theme', theme);
      updateActiveButton(button);
    });
  });
  
  // Load saved theme
  const savedTheme = localStorage.getItem('hotspot-theme') || 'vivid';
  document.documentElement.setAttribute('data-theme', savedTheme);
  const activeButton = document.querySelector(`.theme-switch[data-theme="${savedTheme}"]`);
  if (activeButton) updateActiveButton(activeButton);
}

function updateActiveButton(activeButton) {
  themeButtons.forEach(btn => btn.classList.remove('active'));
  activeButton.classList.add('active');
}

// Setup event listeners
function setupEventListeners() {
  applyVoucherBtn.addEventListener('click', applyVoucher);
  // Handle Enter key in voucher input
  voucherInput.addEventListener('keypress', (e) => {
    if (e.key === 'Enter') applyVoucher();
  });
}