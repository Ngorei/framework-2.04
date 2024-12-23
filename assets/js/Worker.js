// Data Worker untuk pemrosesan berat
self.onmessage = function(e) {
  const { action, data } = e.data;
  console.log('Worker received action:', action);
  console.log('Worker received data:', data);
  
  try {
    switch(action) {
      case 'filter':
        const { items, filters } = data;
        const filtered = items.filter((item) => {
          return Object.entries(filters).every(
            ([filterType, filterValue]) => {
              if (!item.hasOwnProperty(filterType)) {
                return false;
              }
              return String(item[filterType]) === String(filterValue);
            }
          );
        });
        self.postMessage({ action: 'filterComplete', result: filtered });
        break;
        
      case 'search':
        const { items: searchItems, query, searchFields } = data;
        // console.log('Searching with:', { query, searchFields });
        const searched = searchData(searchItems, query, searchFields);
        // console.log('Search result:', searched);
        self.postMessage({ action: 'searchComplete', result: searched });
        break;
        
      case 'processLargeData':
        // Tambahkan logika pemrosesan data besar di sini
        const processedData = processDataWithSlug(data.items);
        self.postMessage({ action: 'processLargeDataComplete', result: processedData });
        break;
        
      // ... case lainnya
    }
  } catch (error) {
    console.error('Worker processing error:', error);
    self.postMessage({ 
      action: 'error', 
      error: error.message 
    });
  }
};

// Fungsi filter data
function filterData(items, keyword, fields) {
  return items.filter(item => 
    fields.some(field => 
      String(item[field]).toLowerCase().includes(keyword.toLowerCase())
    )
  );
}

// Fungsi sort data
function sortData(items, field, direction = 'asc') {
  return [...items].sort((a, b) => {
    const aVal = a[field];
    const bVal = b[field];
    return direction === 'asc' ? 
      String(aVal).localeCompare(String(bVal)) :
      String(bVal).localeCompare(String(aVal));
  });
}

// Fungsi process slug
function processDataWithSlug(items) {
  return items.map(item => ({
    ...item,
    slug: createSlug(item.title || item.name || ''),
    processed: true,
    timestamp: Date.now()
  }));
}

// Fungsi search dengan indexing
function searchData(items, query, searchFields) {
  // Buat search index untuk performa lebih baik
  const searchIndex = new Map();
  
  items.forEach((item, index) => {
    const searchText = searchFields
      .map(field => item[field] ? String(item[field]).toLowerCase() : '')
      .join(' ');
    searchIndex.set(index, searchText);
  });
  
  // Lakukan pencarian menggunakan index
  const queryLower = query.toLowerCase();
  return items.filter((_, index) => 
    searchIndex.get(index).includes(queryLower)
  );
}

// Helper function untuk membuat slug
function createSlug(str) {
  return str
    .toLowerCase()
    .trim()
    .replace(/[^\w\s-]/g, '')
    .replace(/\s+/g, '-')
    .replace(/-+/g, '-');
} 