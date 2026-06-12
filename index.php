<?php
require_once 'config/db.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Urban Stay - Find Your Perfect PG</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .hero-section {
            background: linear-gradient(135deg, #185FA5 0%, #0d3b66 100%);
            color: white;
            text-align: center;
            padding: 4rem 2rem;
        }
        .hero-content h1 {
            font-size: 2.5rem;
            margin-bottom: 1rem;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }
        .search-filter-section {
            background: white;
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }
        .search-input {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 1rem;
            margin-bottom: 1rem;
        }
        .filters {
            display: flex;
            gap: 1rem;
        }
        .filter-select {
            flex: 1;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 1rem;
        }
        .pg-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.5rem;
        }
        .pg-card {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            cursor: pointer;
            transition: transform 0.3s, box-shadow 0.3s;
        }
        .pg-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }
        .pg-card-img {
            width: 100%;
            height: 200px;
            object-fit: cover;
        }
        .pg-card-content {
            padding: 1rem;
        }
        .pg-card-content h3 {
            margin-bottom: 0.5rem;
            color: #185FA5;
        }
        .location {
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
        }
        .price {
            font-weight: bold;
            color: #185FA5;
            margin-bottom: 0.5rem;
        }
        .type {
            display: inline-block;
            background: #e0e7ff;
            padding: 0.25rem 0.75rem;
            border-radius: 15px;
            font-size: 0.8rem;
            margin-bottom: 0.5rem;
        }
        .rating {
            color: #f5a623;
        }
        .no-results {
            text-align: center;
            padding: 3rem;
            background: white;
            border-radius: 10px;
            color: #666;
        }
        .loading {
            text-align: center;
            padding: 3rem;
            font-size: 1.2rem;
            color: #185FA5;
        }
        @media (max-width: 768px) {
            .filters {
                flex-direction: column;
            }
.pg-card-img {
    width: 100%;
    height: 200px;
    object-fit: cover;
    background-color: #f0f0f0;
    display: block;
}

/* Default image placeholder style */
.pg-card-img[src*="default-pg.jpg"] {
    object-fit: contain;
    padding: 20px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}
        }
    </style>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>
    
    <div class="hero-section">
        <div class="hero-content">
            <h1>Find Your Perfect PG Accommodation</h1>
            <p>Safe, comfortable, and affordable paying guest accommodations across the city</p>
        </div>
    </div>
    
    <div class="container">
        <div class="search-filter-section">
            <div class="search-bar">
                <input type="text" id="searchInput" placeholder="Search by location or area..." class="search-input">
            </div>
            <div class="filters">
                <select id="priceFilter" class="filter-select">
                    <option value="">All Prices</option>
                    <option value="0-5000">Below ₹5000</option>
                    <option value="5000-10000">₹5000 - ₹10000</option>
                    <option value="10000-15000">₹10000 - ₹15000</option>
                    <option value="15000+">Above ₹15000</option>
                </select>
                <select id="typeFilter" class="filter-select">
                    <option value="">All Types</option>
                    <option value="Male">Male Only</option>
                    <option value="Female">Female Only</option>
                    <option value="Both">Both</option>
                </select>
            </div>
        </div>
        
        <div id="pgListings" class="pg-grid">
            <div class="loading">Loading PG listings...</div>
        </div>
    </div>
    
    <script>
        // Load PG listings on page load
        document.addEventListener('DOMContentLoaded', function() {
            loadPGListings();
            
            // Add event listeners for filters
            const searchInput = document.getElementById('searchInput');
            const priceFilter = document.getElementById('priceFilter');
            const typeFilter = document.getElementById('typeFilter');
            
            if(searchInput) searchInput.addEventListener('input', loadPGListings);
            if(priceFilter) priceFilter.addEventListener('change', loadPGListings);
            if(typeFilter) typeFilter.addEventListener('change', loadPGListings);
        });
        
        function loadPGListings() {
    const search = document.getElementById('searchInput')?.value || '';
    const price = document.getElementById('priceFilter')?.value || '';
    const type = document.getElementById('typeFilter')?.value || '';
    
    const container = document.getElementById('pgListings');
    if (!container) return;
    
    container.innerHTML = '<div class="loading">Loading PG listings...</div>';
    
    fetch(`ajax/get_pgs.php?search=${encodeURIComponent(search)}&price=${encodeURIComponent(price)}&type=${encodeURIComponent(type)}`)
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            if (data.error) {
                container.innerHTML = `<div class="no-results">Error: ${data.error}</div>`;
                return;
            }
            
            if (!data || data.length === 0) {
                container.innerHTML = '<div class="no-results">No PG listings found. Please check back later.</div>';
                return;
            }
            
            container.innerHTML = data.map(pg => {
                // Check if image exists and is valid
                let imageUrl = 'assets/images/default-pg.jpg';
                if (pg.image && pg.image !== '' && !pg.image.includes('null') && !pg.image.includes('undefined')) {
                    imageUrl = pg.image;
                }
                
                return `
                    <div class="pg-card" onclick="window.location.href='pg-detail.php?id=${pg.id}'">
                        <img src="${imageUrl}" 
                             alt="${escapeHtml(pg.name)}" 
                             class="pg-card-img" 
                             onerror="this.src='assets/images/default-pg.jpg'; this.onerror=null;">
                        <div class="pg-card-content">
                            <h3>${escapeHtml(pg.name)}</h3>
                            <p class="location">📍 ${escapeHtml(pg.address.substring(0, 100))}${pg.address.length > 100 ? '...' : ''}</p>
                            <p class="price">💰 ₹${Number(pg.price_per_month).toLocaleString()}/month</p>
                            <p class="type">${pg.type}</p>
                            <div class="rating">⭐ ${pg.rating || 'New'}</div>
                        </div>
                    </div>
                `;
            }).join('');
        })
        .catch(error => {
            console.error('Error loading PGs:', error);
            container.innerHTML = '<div class="no-results">Error loading PG listings. Please refresh the page.</div>';
        });
}        
        function escapeHtml(str) {
            if (!str) return '';
            return str
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }
    </script>
</body>
</html>