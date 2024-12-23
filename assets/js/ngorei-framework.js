export const filterRow = (data, propertyMap) => {
  return data.map((item) => {
    const newObj = {};
    Object.entries(propertyMap).forEach(([oldKey, newKey]) => {
      if (item.hasOwnProperty(oldKey)) {
        newObj[newKey] = item[oldKey];
      }
    });
    return newObj;
  });
};
const TOKEN_CONFIG = {
    expiresIn: 60 * 60 * 24 * 7, // 7 hari dalam detik
    algorithm: 'AES-CBC',
    keySize: 256
};
export function encryptToken(data,cradensial) {
    const tokenData = {
        ...data,
        exp: Math.floor(Date.now() / 1000) + TOKEN_CONFIG.expiresIn,
        iat: Math.floor(Date.now() / 1000)
    };
    try {
        const jsonString = JSON.stringify(tokenData);
        const keyBytes = CryptoJS.SHA256(cradensial);
        const iv = CryptoJS.lib.WordArray.random(16);
        const encrypted = CryptoJS.AES.encrypt(jsonString, keyBytes, {
            iv: iv,
            mode: CryptoJS.mode.CBC,
            padding: CryptoJS.pad.Pkcs7
        });
        const ivCiphertext = iv.concat(encrypted.ciphertext);
        return encodeURIComponent(CryptoJS.enc.Base64.stringify(ivCiphertext));
    } catch (error) {
        throw new Error('Gagal mengenkripsi data: ' + error.message);
    }
}

export function decryptToken(token,cradensial) {
    try {
        const decodedToken = decodeURIComponent(token);
        const cipherData = CryptoJS.enc.Base64.parse(decodedToken);
        
        const keyBytes = CryptoJS.SHA256(cradensial);
        
        const iv = CryptoJS.lib.WordArray.create(cipherData.words.slice(0, 4));
        const ciphertext = CryptoJS.lib.WordArray.create(cipherData.words.slice(4));
        
        const decrypted = CryptoJS.AES.decrypt(
            { ciphertext: ciphertext },
            keyBytes,
            {
                iv: iv,
                mode: CryptoJS.mode.CBC,
                padding: CryptoJS.pad.Pkcs7
            }
        );
        const decryptedString = decrypted.toString(CryptoJS.enc.Utf8);
        if (!decryptedString) {
            throw new Error('Hasil dekripsi kosong');
        }
        const decoded = JSON.parse(decryptedString);
        
        if (decoded.exp && decoded.exp < Math.floor(Date.now() / 1000)) {
            throw new Error('Token sudah kadaluarsa');
        }
        
        return decoded;
    } catch (error) {
        throw new Error('Token tidak valid: ' + error.message);
    }
}

export function tokenize(userData,cradensial) {
    if (!userData?.payload) {
        throw new Error('Data tidak lengkap');
    }
    const { payload } = userData;
    
    if (typeof payload === 'string') {
        return decryptToken(payload,cradensial);
    } 
    
    if (Array.isArray(payload) || (typeof payload === 'object' && payload !== null)) {
        return encryptToken(userData,cradensial);
    }
    
    throw new Error('Format payload tidak valid');
}
export class Crypto {
  constructor(tokenize) {
    this.init = new Ngorei();
    this.Net = new Ngorei().Network();
  }

  async authenticate(data, callbacks = {}) {
    try {
      // 1. Generate token
      const encrypt = this.init.Tokenize({
        'payload': data.payload,
      }, data.endpoint);

      // Callback untuk hasil token
      if (callbacks.onToken) {
        callbacks.onToken(encrypt);
      }

      // 2. Kirim request
      const response = await this.Net.Brief({
        endpoint: data.endpoint,
        token: encrypt
      });

      // Callback untuk status permintaan
      if (callbacks.onStatus) {
        callbacks.onStatus(response);
      }

      // 3. Decrypt response
      const decrypt = this.init.Tokenize({
        'payload': response.data.token,
      }, data.endpoint);

      // Callback untuk hasil akhir
      if (callbacks.onResult) {
        callbacks.onResult(decrypt);
      }
      
      return decrypt;

    } catch (error) {
      // console.error('Authentication error:', error);
      // throw error;
    }
  }
}
export function Ngorei() {
  return {
     Helper: function() {
       return {
           Encode: function(row) {
              return Encode(row)
           },
           Decode: function(row) {
              return Decode(row)
           },
       }
     },
     Tokenize: function(userData,cradensial) {
        const SECRET_KEY= cradensial.replace(/-/g, "")
        return tokenize(userData,SECRET_KEY)
     },
     Components: function() {
       return {

        Prism: function() {
          wrapCodeWithTerminal();
          window.copyCode = function (button) {
            try {
              // Ambil kode dari elemen
              const pre = button.closest(".terminal").querySelector("pre");
              const code = pre.querySelector("code").innerText;
              // Buat elemen textarea temporary
              const textarea = document.createElement("textarea");
              textarea.value = code;
              textarea.style.position = "fixed"; // Hindari scrolling
              textarea.style.opacity = "0"; // Sembunyikan elemen
              // Tambahkan ke dokumen
              document.body.appendChild(textarea);
              // Select dan copy
              textarea.select();
              document.execCommand("copy");
              // Bersihkan
              document.body.removeChild(textarea);
              // Feedback visual
              const originalText = button.textContent;
              button.innerHTML =
                '<i class="icon-feather-copy"></i> ' + "Tersalin";
              button.classList.add("bg-green-600");
              setTimeout(() => {
                button.innerHTML =
                  '<i class="icon-feather-copy"></i> ' + originalText;
                button.classList.remove("bg-green-600");
              }, 2000);
            } catch (err) {
              console.error("Gagal menyalin:", err);
              alert("Maaf, gagal menyalin kode");
            }
          };
          }
       }
     },
     From: function(row) {
       return {
         Content:function(row){
          return createForm(row)
         },
         Wizard: function(row) {
           return createWizard(row);
         },
         filebrowser: function(row) {
           return filebrowser(row);
         },
       }; 
     },
     Modal: function(row) {
       return {
         Content: function() {
           return createModal(row);
         },
         From: function() {
           return createFromModal(row);
         }
       };
     },
     Render: function() { 
       return {
          View: function(row) {
            return new View(row);
          },
          Tabel: function(row) {
            return {
              Matrix: function(row) {
                return new TabelMatrix(row)
              }
              
            };
          },
          
          SinglePageApp: function(e) {
             const spa = new SinglePageApp();
              return spa.SinglePageApplication(e)
          },
          latSinglePageApp: function(result) {

            const  latSing=latSinglePageApp({
                     'elementById':result.elementById,
                     'endpoint':result.key,
                     'forceReload': false,
                     'data':result.data.data
                    })
                    .then(response => {
                        if (response) {
                           const contentElement = document.getElementById(result.data.elementById);
                           contentElement.innerHTML =response; 
                          
                        } else {
                            console.log('Data tidak ditemukan');
                        }
                    })
                    .catch(error => {
                        console.error('Terjadi kesalahan:', error);
                    });

             return latSing
          },
       }
     },

     Network: function() {
          return {
           tatiye: function() {
            return tatiye
           },
           WebSocket: function() {
             return tatiye
           },
           Queue: function(row) {
            return Queue(row)
           },
           Brief: function(row) {
            return Brief(row)
           },
           Crypto: function() {
            return new Crypto()
           },
           Buckets: function(row) {
            return Buckets(row)
           },
           RTDb: function(callback,token) {
            return RTDb(callback,token)
           },
           filterRow: function(data, propertyMap) {
            return filterRow(data, propertyMap)
           },
           indexDB: function() {
            const db = new classIndexDB();
            return {
              add: async function(row) {
              try {
                const key = row.key;
                const data = row.data;
                const timestamp = Date.now();
                const hasil = await db.saveData(key, data, timestamp);
                const tersimpan = await db.getData(key);
                return tersimpan;
              } catch (error) {
                console.error("Error:", error);
              }
            },
            get: async function(key) {
              try {
                const data = await db.getData(key);
                return data;
              } catch (error) {
                console.error("Error:", error);
              }
            },
            ref: async function() {
              try {
                const allData = await db.getAllData();
                return allData;
              } catch (error) {
                console.error("Error:", error);
              }
            },
            up: async function(key, newData) {
              try {
                await db.updateData(key, newData);
                const updatedData = await db.getData(key);
                return updatedData;
              } catch (error) {
                console.error("Error:", error);
              }
            },
            del: async function(key) {
              try {
                const result = await db.deleteData(key);
                return result;
              } catch (error) {
                console.error("Error:", error);
              }
            },
            latest: async function() {
              try {
                const latestData = await db.getLatestData();
                return latestData;
              } catch (error) {
                console.error("Error:", error);
              }
            }
          };
        },
        localStorage: function() {
          const storage = new classLocalStorage();
          return {
            add: async function(row) {
              try {
                const key = row.key;
                const data = row.data;
                const timestamp = Date.now();
                await storage.saveData(key, data, timestamp);
                const tersimpan = await storage.getData(key);
                return tersimpan;
              } catch (error) {
                console.error("Error:", error);
              }
            },
            get: async function(key) {
              try {
                const data = await storage.getData(key);
                return data;
              } catch (error) {
                console.error("Error:", error);
              }
            },
            ref: async function() {
              try {
                const allData = await storage.getAllData();
                return allData;
              } catch (error) {
                console.error("Error:", error);
              }
            },
            up: async function(key, newData) {
              try {
                await storage.updateData(key, newData);
                const updatedData = await storage.getData(key);
                return updatedData;
              } catch (error) {
                console.error("Error:", error);
              }
            },
            del: async function(key) {
              try {
                const result = await storage.deleteData(key);
                return result;
              } catch (error) {
                console.error("Error:", error);
              }
            },
            latest: async function() {
              try {
                const latestData = await storage.getLatestData();
                return latestData;
              } catch (error) {
                console.error("Error:", error);
              }
            }
          };
        },
        cookies: function() {
          const storage = new classCookies();
          return {
            add: async function(row, options = {}) {
              try {
                const key = row.key;
                const data = row.data;
                const timestamp = Date.now();
                await storage.saveData(key, data, timestamp, options);
                const tersimpan = await storage.getData(key);
                return tersimpan;
              } catch (error) {
                console.error("Error:", error);
              }
            },
            get: async function(key) {
              try {
                const data = await storage.getData(key);
                return data;
              } catch (error) {
                console.error("Error:", error);
              }
            },
            ref: async function() {
              try {
                const allData = await storage.getAllData();
                return allData;
              } catch (error) {
                console.error("Error:", error);
              }
            },
            up: async function(key, newData, options = {}) {
              try {
                await storage.updateData(key, newData, options);
                const updatedData = await storage.getData(key);
                return updatedData;
              } catch (error) {
                console.error("Error:", error);
              }
            },
            del: async function(key, options = {}) {
              try {
                const result = await storage.deleteData(key, options);
                return result;
              } catch (error) {
                console.error("Error:", error);
              }
            },
            latest: async function() {
              try {
                const latestData = await storage.getLatestData();
                return latestData;
              } catch (error) {
                console.error("Error:", error);
              }
            }
          };
        },
        sessionStorage: function() {
          const storage = new classSessionStorage();
          return {
            add: async function(row) {
              try {
                const key = row.key;
                const data = row.data;
                const timestamp = Date.now();
                await storage.saveData(key, data, timestamp);
                const tersimpan = await storage.getData(key);
                return tersimpan;
              } catch (error) {
                console.error("Error:", error);
              }
            },
            get: async function(key) {
              try {
                const data = await storage.getData(key);
                return data;
              } catch (error) {
                console.error("Error:", error);
              }
            },
            ref: async function() {
              try {
                const allData = await storage.getAllData();
                return allData;
              } catch (error) {
                console.error("Error:", error);
              }
            },
            up: async function(key, newData) {
              try {
                await storage.updateData(key, newData);
                const updatedData = await storage.getData(key);
                return updatedData;
              } catch (error) {
                console.error("Error:", error);
              }
            },
            del: async function(key) {
              try {
                const result = await storage.deleteData(key);
                return result;
              } catch (error) {
                console.error("Error:", error);
              }
            },
            latest: async function() {
              try {
                const latestData = await storage.getLatestData();
                return latestData;
              } catch (error) {
                console.error("Error:", error);
              }
            }
          };
        }
      }
 
     }
  }
}

// Mendefinisikan module pattern untuk Network
if (typeof define === "function" && define.amd) {
  // AMD
  define([], () => Ngorei);
} else if (typeof module === "object" && module.exports) {
  // CommonJS/Node.js
  module.exports = Ngorei;
} else {
  // Browser global
  window.Ngorei = Ngorei;
  window.dbs = new Ngorei(); // Instance default

}
//Komponen Network
 // Fungsi untuk mengelola cookie di browser
export function cookies(element) {
  // Fungsi untuk mengatur cookie
  const setCookie = (name, value, days) => {
    let expires = "";
    if (days) {
      const date = new Date();
      date.setTime(date.getTime() + days * 24 * 60 * 60 * 1000);
      expires = "; expires=" + date.toUTCString();
    }
    document.cookie = name + "=" + value + expires + "; path=/";
  };

  // Fungsi untuk mendapatkan nilai cookie (dengan decode)
  const getCookie = (name) => {
    const nameEQ = name + "=";
    const ca = document.cookie.split(";");
    for (let i = 0; i < ca.length; i++) {
      let c = ca[i];
      while (c.charAt(0) === " ") c = c.substring(1, c.length);
      if (c.indexOf(nameEQ) === 0) {
        // Tambahkan decodeURIComponent untuk decode nilai cookie
        return decodeURIComponent(c.substring(nameEQ.length, c.length));
      }
    }
    return null;
  };

  // Fungsi untuk menghapus cookie
  const deleteCookie = (name) => {
    document.cookie = name + "=; Max-Age=-99999999;";
  };

  return {
    set: setCookie,
    get: getCookie,
    delete: deleteCookie,
  };
}

const cookieManager = cookies();
let HOSTSOKET; // Deklarasi di luar blok
const HOSTW = window.location.host.split('.')[0];
if (HOSTW === '192') {
    HOSTSOKET = window.location.host;
} else {
    HOSTSOKET = '127.0.0.1';
}
// Membuat koneksi WebSocket
const WS_HOST =HOSTSOKET;
const WS_PORT =8080;
export const createWebSocketConnection = () => {
  return new WebSocket(`ws://${WS_HOST}:${WS_PORT}`);
};



const app = {
  app: "Ngorei",
  version: "v1.0.4",
  copyright: "2013-2024",
  vid: cookieManager.get("VID"),
  url: window.location.origin,
};
export default app;
//Komponen Network
export class classIndexDB {
  constructor(dbName = "Database", dbVersion = 1, storeName = "Data") {
    this.dbName = dbName;
    this.dbVersion = dbVersion;
    this.storeName = storeName;
  }

  openDB() {
    return new Promise((resolve, reject) => {
      const request = indexedDB.open(this.dbName, this.dbVersion);

      request.onerror = (event) =>
        reject("Error membuka database: " + event.target.error);

      request.onsuccess = (event) => resolve(event.target.result);

      request.onupgradeneeded = (event) => {
        const db = event.target.result;
        const objectStore = db.createObjectStore(this.storeName, {
          keyPath: "key",
        });
        objectStore.createIndex("updated_at", "updated_at", { unique: false });
      };
    });
  }

  async saveData(key, data, updated_at) {
    const db = await this.openDB();
    return new Promise((resolve, reject) => {
      const transaction = db.transaction([this.storeName], "readwrite");
      const store = transaction.objectStore(this.storeName);
      const getRequest = store.get(key);

      getRequest.onsuccess = (event) => {
        const existingData = event.target.result;
        if (existingData && existingData.updated_at >= updated_at) {
          resolve("Data sudah yang terbaru");
        } else {
          const updateRequest = store.put({
            key: key,
            data: data,
            updated_at: updated_at,
          });
          updateRequest.onsuccess = () =>
            resolve("Data berhasil disimpan/diperbarui");
          updateRequest.onerror = (event) =>
            reject("Error menyimpan/memperbarui data: " + event.target.error);
        }
      };

      getRequest.onerror = (event) =>
        reject("Error memeriksa data: " + event.target.error);
    });
  }

  async getData(key) {
    const db = await this.openDB();
    return new Promise((resolve, reject) => {
      const transaction = db.transaction([this.storeName], "readonly");
      const store = transaction.objectStore(this.storeName);
      const request = store.get(key);

      request.onsuccess = (event) => resolve(event.target.result);
      request.onerror = (event) =>
        reject("Error mengambil data: " + event.target.error);
    });
  }

  async deleteData(key) {
    const db = await this.openDB();
    return new Promise((resolve, reject) => {
      const transaction = db.transaction([this.storeName], "readwrite");
      const store = transaction.objectStore(this.storeName);
      const request = store.delete(key);

      request.onsuccess = () => resolve("Data berhasil dihapus");
      request.onerror = (event) =>
        reject("Error menghapus data: " + event.target.error);
    });
  }
  async getAllData() {
    const db = await this.openDB();
    return new Promise((resolve, reject) => {
      const transaction = db.transaction([this.storeName], "readonly");
      const store = transaction.objectStore(this.storeName);
      const request = store.getAll();

      request.onsuccess = (event) => resolve(event.target.result);
      request.onerror = (event) =>
        reject("Error mengambil semua data: " + event.target.error);
    });
  }

  async updateData(key, newData) {
    const db = await this.openDB();
    return new Promise((resolve, reject) => {
      const transaction = db.transaction([this.storeName], "readwrite");
      const store = transaction.objectStore(this.storeName);
      const getRequest = store.get(key);

      getRequest.onsuccess = (event) => {
        const existingData = event.target.result;
        if (!existingData) {
          reject("Data tidak ditemukan");
          return;
        }

        const updatedObject = {
          key: key,
          data: { ...existingData.data, ...newData },
          updated_at: Date.now()
        };

        const updateRequest = store.put(updatedObject);
        updateRequest.onsuccess = () => resolve("Data berhasil diupdate");
        updateRequest.onerror = (event) => 
          reject("Error mengupdate data: " + event.target.error);
      };

      getRequest.onerror = (event) =>
        reject("Error memeriksa data: " + event.target.error);
    });
  }

  async getLatestData() {
    const db = await this.openDB();
    return new Promise((resolve, reject) => {
      const transaction = db.transaction([this.storeName], "readonly");
      const store = transaction.objectStore(this.storeName);
      const index = store.index("updated_at");
      const request = index.openCursor(null, "prev");

      request.onsuccess = (event) => {
        const cursor = event.target.result;
        if (cursor) {
          resolve(cursor.value);
        } else {
          resolve(null);
        }
      };
      request.onerror = (event) =>
        reject("Error mengambil data terbaru: " + event.target.error);
    });
  }
}

export class classLocalStorage {
  constructor(prefix = "") {
    this.prefix = prefix;
  }

  saveData(key, data, updated_at) {
    return new Promise((resolve) => {
      const fullKey = this.prefix + key;
      const existingData = localStorage.getItem(fullKey);
      
      if (existingData) {
        const parsed = JSON.parse(existingData);
        if (parsed.updated_at >= updated_at) {
          resolve("Data sudah yang terbaru");
          return;
        }
      }

      const saveObject = {
        key: key,
        data: data,
        updated_at: updated_at
      };
      
      localStorage.setItem(fullKey, JSON.stringify(saveObject));
      resolve("Data berhasil disimpan/diperbarui");
    });
  }

  getData(key) {
    return new Promise((resolve) => {
      const data = localStorage.getItem(this.prefix + key);
      resolve(data ? JSON.parse(data) : null);
    });
  }

  deleteData(key) {
    return new Promise((resolve) => {
      localStorage.removeItem(this.prefix + key);
      resolve("Data berhasil dihapus");
    });
  }

  getAllData() {
    return new Promise((resolve) => {
      const allData = [];
      for (let i = 0; i < localStorage.length; i++) {
        const key = localStorage.key(i);
        if (key.startsWith(this.prefix)) {
          const data = JSON.parse(localStorage.getItem(key));
          allData.push(data);
        }
      }
      resolve(allData);
    });
  }

  updateData(key, newData) {
    return new Promise((resolve, reject) => {
      const fullKey = this.prefix + key;
      const existingData = localStorage.getItem(fullKey);
      
      if (!existingData) {
        reject("Data tidak ditemukan");
        return;
      }

      const parsed = JSON.parse(existingData);
      const updatedObject = {
        key: key,
        data: { ...parsed.data, ...newData },
        updated_at: Date.now()
      };

      localStorage.setItem(fullKey, JSON.stringify(updatedObject));
      resolve("Data berhasil diupdate");
    });
  }

  async getLatestData() {
    return new Promise((resolve) => {
      let latestData = null;
      let latestTimestamp = 0;
      
      for (let i = 0; i < localStorage.length; i++) {
        const key = localStorage.key(i);
        if (key.startsWith(this.prefix)) {
          const data = JSON.parse(localStorage.getItem(key));
          if (data.updated_at > latestTimestamp) {
            latestTimestamp = data.updated_at;
            latestData = data;
          }
        }
      }
      resolve(latestData);
    });
  }
}

export class classCookies {
  constructor(prefix = "app_") {
    this.prefix = prefix;
  }

  saveData(key, data, updated_at, options = {}) {
    return new Promise((resolve) => {
      const fullKey = this.prefix + key;
      const existingData = this.getCookie(fullKey);
      
      if (existingData) {
        const parsed = JSON.parse(existingData);
        if (parsed.updated_at >= updated_at) {
          resolve("Data sudah yang terbaru");
          return;
        }
      }

      const saveObject = {
        key: key,
        data: data,
        updated_at: updated_at
      };
      
      // Default options
      const defaultOptions = {
        expires: 365, // hari
        path: '/',
        secure: false,
        sameSite: 'Lax'
      };

      const cookieOptions = { ...defaultOptions, ...options };
      
      // Set expires
      let expires = '';
      if (cookieOptions.expires) {
        const date = new Date();
        date.setTime(date.getTime() + (cookieOptions.expires * 24 * 60 * 60 * 1000));
        expires = `expires=${date.toUTCString()};`;
      }

      // Build cookie string
      let cookieString = `${fullKey}=${encodeURIComponent(JSON.stringify(saveObject))};${expires}`;
      cookieString += `path=${cookieOptions.path};`;
      
      if (cookieOptions.secure) cookieString += 'secure;';
      if (cookieOptions.sameSite) cookieString += `sameSite=${cookieOptions.sameSite};`;

      document.cookie = cookieString;
      resolve("Data berhasil disimpan/diperbarui");
    });
  }

  getCookie(key) {
    const name = this.prefix + key + "=";
    const decodedCookie = decodeURIComponent(document.cookie);
    const cookieArray = decodedCookie.split(';');
    
    for (let i = 0; i < cookieArray.length; i++) {
      let cookie = cookieArray[i].trim();
      if (cookie.indexOf(name) === 0) {
        return cookie.substring(name.length);
      }
    }
    return null;
  }

  getData(key) {
    return new Promise((resolve) => {
      const data = this.getCookie(key);
      resolve(data ? JSON.parse(data) : null);
    });
  }

  deleteData(key, options = {}) {
    return new Promise((resolve) => {
      const fullKey = this.prefix + key;
      const defaultOptions = {
        path: '/',
        secure: false,
        sameSite: 'Lax'
      };
      
      const cookieOptions = { ...defaultOptions, ...options };
      
      // Set expired date to past
      document.cookie = `${fullKey}=;expires=Thu, 01 Jan 1970 00:00:00 GMT;path=${cookieOptions.path}`;
      resolve("Data berhasil dihapus");
    });
  }

  getAllData() {
    return new Promise((resolve) => {
      const allData = [];
      const decodedCookie = decodeURIComponent(document.cookie);
      const cookieArray = decodedCookie.split(';');
      
      for (let i = 0; i < cookieArray.length; i++) {
        const cookie = cookieArray[i].trim();
        if (cookie.indexOf(this.prefix) === 0) {
          const equalPos = cookie.indexOf('=');
          const value = cookie.substring(equalPos + 1);
          try {
            const data = JSON.parse(value);
            allData.push(data);
          } catch (e) {
            console.error('Error parsing cookie:', e);
          }
        }
      }
      resolve(allData);
    });
  }

  updateData(key, newData, options = {}) {
    return new Promise(async (resolve, reject) => {
      const existingData = await this.getData(key);
      
      if (!existingData) {
        reject("Data tidak ditemukan");
        return;
      }

      const updatedObject = {
        key: key,
        data: { ...existingData.data, ...newData },
        updated_at: Date.now()
      };

      await this.saveData(key, updatedObject.data, updatedObject.updated_at, options);
      resolve("Data berhasil diupdate");
    });
  }

  async getLatestData() {
    return new Promise((resolve) => {
      let latestData = null;
      let latestTimestamp = 0;
      const decodedCookie = decodeURIComponent(document.cookie);
      const cookieArray = decodedCookie.split(';');
      
      for (let i = 0; i < cookieArray.length; i++) {
        const cookie = cookieArray[i].trim();
        if (cookie.indexOf(this.prefix) === 0) {
          const equalPos = cookie.indexOf('=');
          const value = cookie.substring(equalPos + 1);
          try {
            const data = JSON.parse(value);
            if (data.updated_at > latestTimestamp) {
              latestTimestamp = data.updated_at;
              latestData = data;
            }
          } catch (e) {
            console.error('Error parsing cookie:', e);
          }
        }
      }
      resolve(latestData);
    });
  }
}

export class classSessionStorage {
  constructor(prefix = "app_") {
    this.prefix = prefix;
  }

  saveData(key, data, updated_at) {
    return new Promise((resolve) => {
      const fullKey = this.prefix + key;
      const existingData = sessionStorage.getItem(fullKey);
      
      if (existingData) {
        const parsed = JSON.parse(existingData);
        if (parsed.updated_at >= updated_at) {
          resolve("Data sudah yang terbaru");
          return;
        }
      }

      const saveObject = {
        key: key,
        data: data,
        updated_at: updated_at
      };
      
      sessionStorage.setItem(fullKey, JSON.stringify(saveObject));
      resolve("Data berhasil disimpan/diperbarui");
    });
  }

  getData(key) {
    return new Promise((resolve) => {
      const data = sessionStorage.getItem(this.prefix + key);
      resolve(data ? JSON.parse(data) : null);
    });
  }

  deleteData(key) {
    return new Promise((resolve) => {
      sessionStorage.removeItem(this.prefix + key);
      resolve("Data berhasil dihapus");
    });
  }

  getAllData() {
    return new Promise((resolve) => {
      const allData = [];
      for (let i = 0; i < sessionStorage.length; i++) {
        const key = sessionStorage.key(i);
        if (key.startsWith(this.prefix)) {
          const data = JSON.parse(sessionStorage.getItem(key));
          allData.push(data);
        }
      }
      resolve(allData);
    });
  }

  updateData(key, newData) {
    return new Promise((resolve, reject) => {
      const fullKey = this.prefix + key;
      const existingData = sessionStorage.getItem(fullKey);
      
      if (!existingData) {
        reject("Data tidak ditemukan");
        return;
      }

      const parsed = JSON.parse(existingData);
      const updatedObject = {
        key: key,
        data: { ...parsed.data, ...newData },
        updated_at: Date.now()
      };

      sessionStorage.setItem(fullKey, JSON.stringify(updatedObject));
      resolve("Data berhasil diupdate");
    });
  }

  async getLatestData() {
    return new Promise((resolve) => {
      let latestData = null;
      let latestTimestamp = 0;
      
      for (let i = 0; i < sessionStorage.length; i++) {
        const key = sessionStorage.key(i);
        if (key.startsWith(this.prefix)) {
          const data = JSON.parse(sessionStorage.getItem(key));
          if (data.updated_at > latestTimestamp) {
            latestTimestamp = data.updated_at;
            latestData = data;
          }
        }
      }
      resolve(latestData);
    });
  }
}


/**
 * @class Ngorei
 * @description Kelas utama untuk manajemen komponen dan DOM
 */
export  class NgoreiDOM {
  constructor() {
    this.DOM = new TDSDOM();

    this.Components = function() {
      return {

      };
    };
  }
}

/**
 * @class View
 * @description Kelas untuk menangani view dan template
 */
class View {
  constructor(row) {
    if (!row || typeof row !== "object") {
      throw new Error("Parameter row harus berupa object");
    }

    this.data = row;
    const self = this;
    const firstKey = Object.keys(row.data)[0];
    const sID = "[@" + firstKey + "]";
    const eID = "[/" + firstKey + "]";
    const rowID = firstKey;

    // Inisialisasi TDSDOM
    const domManager = new TDSDOM();

    // Implementasi deep copy yang aman
    function deepCopy(obj) {
      if (obj === null || typeof obj !== 'object') return obj;
      
      const copy = Array.isArray(obj) ? [] : {};
      
      for (let key in obj) {
        if (Object.prototype.hasOwnProperty.call(obj, key)) {
          copy[key] = deepCopy(obj[key]);
        }
      }
      
      return copy;
    }

    // Validasi dan sanitasi data
    function validateAndSanitizeData(data) {
      if (!data || typeof data !== 'object') {
        throw new Error('Data tidak valid');
      }
      // Tambahkan validasi lain sesuai kebutuhan
      return data;
    }

    // Fungsi untuk mengurutkan data
    function sortData(data, order = 'ASC', sortBy = 'id') {
      if (!Array.isArray(data)) return data;
      
      return [...data].sort((a, b) => {
        const valueA = a[sortBy];
        const valueB = b[sortBy];
        
        if (order === 'ASC') {
          return valueA > valueB ? 1 : -1;
        } else {
          return valueA < valueB ? 1 : -1;
        }
      });
    }

    const originalData = validateAndSanitizeData(row.data);
    // Tambahkan pengurutan data
    const sortedData = sortData(originalData[rowID], row.sortOrder || 'ASC', row.sortBy || 'id');
    originalData[rowID] = sortedData;
    const data = deepCopy(originalData);
    const pageLimit = row.order || 10;

    // Validasi element
    const oldElement = document.getElementById(row.elementById);
    if (!oldElement) {
      throw new Error(`Element dengan ID ${row.elementById} tidak ditemukan`);
    }

    // Buat template element
    const templateElement = createTemplateElement(
      firstKey,
      row.elementById,
      oldElement
    );
    const contentElement = createContentElement(
      row.elementById,
      oldElement.className
    );

    // Setup template
    const template = sID + templateElement.innerHTML + eID;

    // Cache DOM elements dan event listeners untuk cleanup
    const domElements = {
      template: templateElement,
      content: contentElement,
      searchInput: row.search ? document.getElementById(row.search) : null,
    };

    /**
     * Membuat element template
     */
    function createTemplateElement(firstKey, elementById, oldElement) {
      const template = document.createElement("script");
      template.type = "text/template";
      template.id = firstKey + "_" + elementById;
      template.innerHTML = oldElement.innerHTML;
      oldElement.parentNode.replaceChild(template, oldElement);
      return template;
    }

    /**
     * Membuat element konten
     */
    function createContentElement(elementById, className) {
      const content = document.createElement("div");
      content.id = elementById + "_content";
      if (className) content.className = className;
      templateElement.parentNode.insertBefore(
        content,
        templateElement.nextSibling
      );
      return content;
    }

    /**
     * @param {number} page - Nomor halaman
     * @returns {Object} Data untuk halaman tertentu
     */
    function curPage(page = 1) {
      const startIndex = (page - 1) * pageLimit;
      const currentData = data[rowID];
      //console.log('Current data:', currentData);
      const slicedData = currentData.slice(startIndex, startIndex + pageLimit);
      //console.log('Sliced data:', slicedData);
      return { [rowID]: slicedData };
    }

    /**
     * @param {string} str - String yang akan dikonversi menjadi slug
     * @returns {string} Slug yang dihasilkan
     */
    function createSlug(str) {
      return str
        .toLowerCase()
        .trim()
        .replace(/[^\w\s-]/g, "")
        .replace(/\s+/g, "-")
        .replace(/-+/g, "-");
    }

    /**
     * Memproses data dengan menambahkan slug
     */
    function processDataWithSlug(data) {
      return data.map((item) => ({
        ...item,
        slug: item.href ? createSlug(item.href) : null,
      }));
    }

    // Inisialisasi data dengan slug
    if (data[rowID]) {
      data[rowID] = processDataWithSlug(data[rowID]);
    }

    // Pindahkan deklarasi currentPage dan totalPages ke atas sebelum digunakan
    let currentPage = 1;
    const totalPages = Math.ceil(data[rowID].length / pageLimit);

    // Cache untuk template yang sudah dirender
    const templateCache = new Map();

    // Cache untuk fragment DOM
    const fragmentCache = new Map();

    /**
     * Optimasi render dengan caching
     */
    function optimizedRender(data, templateId) {
      const cacheKey = JSON.stringify(data) + templateId;

      // Cek cache
      if (templateCache.has(cacheKey)) {
        return templateCache.get(cacheKey);
      }

      // Render template menggunakan TDSDOM
      const rendered = domManager.render(template, data, templateElement);

      // Simpan ke cache dengan batasan ukuran
      if (templateCache.size > 100) {
        const firstKey = templateCache.keys().next().value;
        templateCache.delete(firstKey);
      }
      templateCache.set(cacheKey, rendered);

      return rendered;
    }

    /**
     * Fragment caching untuk performa
     */
    function createCachedFragment(items, templateId) {
      const cacheKey = templateId + items.length;

      if (fragmentCache.has(cacheKey)) {
        return fragmentCache.get(cacheKey).cloneNode(true);
      }

      const fragment = document.createDocumentFragment();
      items.forEach((item) => {
        const rendered = optimizedRender({ [rowID]: [item] }, templateId);
        const div = document.createElement("div");
        div.innerHTML = rendered;
        fragment.appendChild(div.firstChild);
      });

      fragmentCache.set(cacheKey, fragment.cloneNode(true));
      return fragment;
    }

    /**
     * @param {Object} pageData - Data yang akan dirender
     */
    function renderData(pageData) {
      requestAnimationFrame(() => {
        // Cache DOM queries
        const content = contentElement;
        if (!content) {
          console.error("Content element tidak ditemukan");
          return;
        }

        // Validasi data
        if (!pageData || !pageData[rowID]) {
          console.error("Data tidak valid:", pageData);
          return;
        }

        try {
          // Clear content terlebih dahulu
          content.innerHTML = "";

          const batchSize = 50;
          let currentBatch = 0;

          function processBatch() {
            const batchData = {
              [rowID]: pageData[rowID].slice(
                currentBatch,
                currentBatch + batchSize
              ),
            };

            // Proses data dengan slug
            if (batchData[rowID]) {
              batchData[rowID] = processDataWithSlug(batchData[rowID]);
            }

            // Gunakan cache untuk optimasi
            const cacheKey = JSON.stringify(batchData) + rowID;
            let rendered;

            if (templateCache.has(cacheKey)) {
              rendered = templateCache.get(cacheKey);
            } else {
              // Menggunakan instance TDSDOM untuk render
              rendered = domManager.render(template, batchData, templateElement);
              templateCache.set(cacheKey, rendered);
            }

            // Buat fragment untuk performa lebih baik
            const fragment = domManager.parse(rendered);

            // Append fragment ke content
            content.appendChild(fragment);

            currentBatch += batchSize;

            // Proses batch selanjutnya jika masih ada
            if (currentBatch < pageData[rowID].length) {
              requestAnimationFrame(processBatch);
            } else {
              // Update pagination setelah selesai
              requestAnimationFrame(updatePaginationUI);
            }
          }

          // Mulai proses batch pertama
          processBatch();
        } catch (error) {
          console.error("Error saat render data:", error);
          // Fallback ke render biasa jika terjadi error
          const rendered = domManager.render(template, pageData, templateElement);
          content.innerHTML = rendered;
        }
      });
    }

    // Tambahkan fungsi untuk virtual scrolling jika diperlukan
    function setupVirtualScroll() {
      if (!row.virtualScroll) return;

      const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) {
            // Load more data when reaching bottom
            if (currentPage < totalPages) {
              currentPage++;
              renderData(curPage(currentPage));
            }
          }
        });
      });

      // Observe last item
      const lastItem = contentElement.lastElementChild;
      if (lastItem) {
        observer.observe(lastItem);
      }

      // Cleanup
      return () => observer.disconnect();
    }

    // Cache DOM queries dan kalkulasi yang sering digunakan
    const cachedData = {
      totalPages: Math.ceil(data[rowID].length / pageLimit),
      searchDebounceTimer: null,
      // Cache DOM elements yang sering digunakan
      paginationElement:
        row.hasOwnProperty("pagination") && row.pagination !== false
          ? document.getElementById(row.pagination)
          : null,
      searchInput:
        row.hasOwnProperty("search") && row.search !== false
          ? document.getElementById(row.search)
          : null,
      // Tambahkan filter select
      filterSelect:
        row.hasOwnProperty("filter") && row.filter !== false
          ? document.getElementById(row.filter)
          : null,
    };

    /**
     * Fungsi debounce untuk search dengan cleanup
     */
    function debounceSearch(fn, delay = 300) {
      let timer = null;
      return function (...args) {
        if (timer) clearTimeout(timer);
        timer = setTimeout(() => {
          timer = null;
          fn.apply(this, args);
        }, delay);
      };
    }

    // Tambahkan fungsi untuk membuat indeks pencarian
    function createSearchIndex(data, searchableFields) {
      const searchIndex = new Map();

      data.forEach((item, index) => {
        let searchText = searchableFields
          .map((field) => {
            return item[field] ? String(item[field]).toLowerCase() : "";
          })
          .join(" ");

        searchIndex.set(index, searchText);
      });

      return searchIndex;
    }

    // Modifikasi memoizedFilter
    const memoizedFilter = (function () {
      const cache = new Map();
      let searchIndex = null;

      return function (keyword, data, searchableFields) {
        const cacheKey = keyword.trim().toLowerCase();
        if (cache.has(cacheKey)) return cache.get(cacheKey);

        // Buat indeks jika belum ada
        if (!searchIndex) {
          searchIndex = createSearchIndex(data, searchableFields);
        }

        const filtered = data.filter((item, index) => {
          const indexedText = searchIndex.get(index);
          return indexedText.includes(cacheKey);
        });

        cache.set(cacheKey, filtered);
        if (cache.size > 100) {
          const firstKey = cache.keys().next().value;
          cache.delete(firstKey);
        }
        return filtered;
      };
    })();

    function debounceAndThrottle(fn, delay = 300, throttleDelay = 100) {
      let debounceTimer;
      let throttleTimer;
      let lastRun = 0;

      return function (...args) {
        // Clear existing debounce timer
        if (debounceTimer) clearTimeout(debounceTimer);

        // Throttle check
        const now = Date.now();
        if (now - lastRun >= throttleDelay) {
          fn.apply(this, args);
          lastRun = now;
        } else {
          // Debounce
          debounceTimer = setTimeout(() => {
            fn.apply(this, args);
            lastRun = Date.now();
          }, delay);
        }
      };
    }

    // Deklarasikan worker di level yang tepat
    let worker = null;
    /**
     * Inisialisasi Web Worker
     */
    function initializeWorker() {
      try {
        // Inisialisasi Web Worker dengan path absolut
        const workerPath = new URL(
          app.url + "/js/Worker.js",
          window.location.origin
        ).href;
        //console.log('Worker path:', workerPath);

        worker = new Worker(workerPath);
        //console.log('Worker created successfully');

        // Setup worker message handler
        worker.onmessage = function (e) {
          const { action, result } = e.data;
          //console.log('Worker response:', { action, resultLength: result?.length });

          switch (action) {
            case "filterComplete":
              //console.log('Filter complete:', result);
              data[rowID] = result;
              currentPage = 1;
              renderData(curPage(1));
              break;

            case "searchComplete":
              //console.log('Search complete:', result);
              data[rowID] = result;
              currentPage = 1;
              renderData(curPage(1));
              updatePaginationUI();
              break;

            case "error":
              console.error("Worker error:", result);
              break;
          }
        };

        // Setup error handler
        worker.onerror = function (error) {
          console.error("Worker error:", error);
          worker = null; // Reset worker on error
        };
      } catch (error) {
        //console.error('Failed to create worker:', error);
        worker = null; // Fallback untuk browser yang tidak mendukung Worker
      }
    }

    // Modifikasi fungsi yang menggunakan worker untuk handle fallback
    function handleSearch(keyword, searchFields) {
      if (worker) {
        // Gunakan worker jika tersedia
        worker.postMessage({
          action: "search",
          data: {
            items: originalData[rowID],
            query: keyword,
            searchFields: searchFields,
          },
        });
      } else {
        // Fallback ke proses synchronous
        const searched = searchData(originalData[rowID], keyword, searchFields);
        handleSearchComplete(searched);
      }
    }

    function handleFilter(value, fields) {
      if (worker) {
        // Gunakan worker jika tersedia
        worker.postMessage({
          action: "filter",
          data: {
            items: originalData[rowID],
            keyword: value,
            fields: fields,
          },
        });
      } else {
        // Fallback ke proses synchronous
        const filtered = filterData(originalData[rowID], value, fields);
        handleFilterComplete(filtered);
      }
    }

    // Modifikasi setupSearch
    function setupSearch() {
      if (!row.hasOwnProperty("search") || !row.search) return;

      const searchInput = document.getElementById(row.search);
      if (!searchInput) return;

      const searchHandler = debounceAndThrottle(
        function (event) {
          const keyword = event.target.value.trim();

          if (keyword.length < 2) {
            data[rowID] = [...originalData[rowID]];
            renderData(curPage(1));
            return;
          }

          handleSearch(
            keyword,
            row.searchableFields || Object.keys(originalData[rowID][0] || {})
          );
        },
        300,
        100
      );

      searchInput.addEventListener("input", searchHandler);
    }

    // Modifikasi setupFilter
    function setupFilter() {
      if (!row.hasOwnProperty("filter")) return;

      const filterSelect = document.getElementById(row.filter);
      if (!filterSelect) return;

      filterSelect.addEventListener("change", function (event) {
        const value = event.target.value;

        if (value === "all") {
          data[rowID] = [...originalData[rowID]];
          renderData(curPage(1));
          return;
        }

        handleFilter(value, [row.filterBy]);
      });
    }

    // Modifikasi destroy untuk cleanup worker
    this.destroy = function () {
      if (worker) {
        worker.terminate();
        worker = null;
      }
      // ... cleanup lainnya
    };

    // Inisialisasi _activeFilters di awal
    this._activeFilters = {};

    /**
     * Setup filter select untuk multiple filter
     */
    const setupFilterSelect = () => {
      if (!row.hasOwnProperty("filterBy")) {
        return false;
      }

      // Support untuk multiple filter
      const filterBy = Array.isArray(row.filterBy)
        ? row.filterBy
        : [row.filterBy];

      // Gunakan this langsung karena arrow function
      const handleFilter = (event) => {
        const selectedValue = event.target.value;
        const filterType = event.target.getAttribute("data-filter-type");

        // Reset ke halaman pertama saat filter berubah
        currentPage = 1;

        // Update nilai filter aktif
        if (selectedValue === "all") {
          delete this._activeFilters[filterType];
        } else {
          this._activeFilters[filterType] = selectedValue;
        }

        requestAnimationFrame(() => {
          // Reset data terlebih dahulu
          data[rowID] = [...originalData[rowID]];

          // Filter data berdasarkan semua filter yang aktif
          if (Object.keys(this._activeFilters).length > 0) {
            data[rowID] = data[rowID].filter((item) => {
              return Object.entries(this._activeFilters).every(
                ([filterType, filterValue]) => {
                  if (!item.hasOwnProperty(filterType)) {
                    console.warn(
                      `Properti "${filterType}" tidak ditemukan pada item:`,
                      item
                    );
                    return false;
                  }
                  return String(item[filterType]) === String(filterValue);
                }
              );
            });
          }

          // Update total pages berdasarkan data yang sudah difilter
          const totalItems = data[rowID].length;
          cachedData.totalPages = Math.ceil(totalItems / pageLimit);

          // Pastikan current page valid
          if (currentPage > cachedData.totalPages) {
            currentPage = cachedData.totalPages || 1;
          }

          // Batch DOM updates
          const updates = () => {
            // Render data halaman pertama
            renderData(curPage(currentPage));

            // Update tampilan pagination
            if (cachedData.paginationElement) {
              updatePaginationUI();
            }
          };

          requestAnimationFrame(updates);
        });
      };

      // Setup event listeners untuk setiap filter select
      filterBy.forEach((filterType) => {
        const selectElement = document.getElementById(filterType);
        if (selectElement) {
          selectElement.setAttribute("data-filter-type", filterType);

          // Cleanup dan setup event listener
          selectElement.removeEventListener("change", handleFilter);
          selectElement.addEventListener("change", handleFilter);

          if (!cachedData.filterElements) {
            cachedData.filterElements = [];
          }
          cachedData.filterElements.push({
            element: selectElement,
            handler: handleFilter,
            type: filterType,
          });
        } else {
          console.warn(
            `Element filter dengan ID "${filterType}" tidak ditemukan`
          );
        }
      });

      return true;
    };

    // Setup fitur-fitur dasar
    setupSearch();
    setupFilter();
    setupLazyLoading();

    // Inisialisasi Web Worker
    initializeWorker();

    // Panggil setupFilterSelect setelah didefinisikan
    setupFilterSelect();

    // Initial render
    renderData(curPage(1));

    /**
     * Cleanup method yang lebih komprehensif
     */
    this.destroy = function () {
      if (cachedData.searchInput) {
        cachedData.searchInput.removeEventListener("input", handleSearch);
      }

      // Cleanup untuk filter
      if (cachedData.filterElements) {
        cachedData.filterElements.forEach(({ element, handler }) => {
          element.removeEventListener("change", handler);
        });
      }

      if (cachedData.searchDebounceTimer) {
        clearTimeout(cachedData.searchDebounceTimer);
      }

      // Clear memoization cache
      memoizedFilter.cache = new Map();

      // Cleanup DOM elements
      domElements.content?.remove();
      domElements.template?.remove();

      // Clear cached data
      Object.keys(cachedData).forEach((key) => {
        cachedData[key] = null;
      });
    };

    /**
     * @param {Function} callback - Callback untuk memproses element
     */
    View.prototype.Element = function (callback) {
      if (typeof callback !== "function") {
        throw new Error("Parameter callback harus berupa function");
      }
      const filteredData = [...this.data.data[Object.keys(this.data.data)[0]]];
      callback(filteredData);
    };

    /**
     * Membuat element pagination jika belum ada
     */
    function createPaginationElement() {
      // Cek apakah pagination didefinisikan dan tidak false
      if (!row.hasOwnProperty("pagination") || row.pagination === false) {
        return null;
      }

      const paginationID = row.pagination;

      // Cek apakah element dengan ID yang sesuai ada di HTML
      let paginationElement = document.getElementById(paginationID);

      if (!paginationID || !paginationElement) {
        console.warn("Element pagination tidak ditemukan atau ID tidak sesuai");
        return null;
      }

      // Pastikan element memiliki class pagination
      if (!paginationElement.classList.contains("pagination")) {
        paginationElement.classList.add("pagination");
      }

      return paginationElement;
    }

    /**
     * Membuat dan memperbarui UI pagination
     */
    function updatePaginationUI() {
      // Cek apakah pagination didefinisikan dan tidak false
      if (!row.hasOwnProperty("pagination") || row.pagination === false) {
        return false;
      }

      const paginationList = createPaginationElement();
      if (!paginationList) return false;

      // Reset pagination content
      paginationList.innerHTML = "";

      // Hitung ulang total pages berdasarkan data yang sudah difilter
      const filteredData = getFilteredData();
      const totalItems = filteredData.length;
      const currentTotalPages = Math.ceil(totalItems / pageLimit);

      // Validasi current page
      if (currentPage > currentTotalPages) {
        currentPage = currentTotalPages || 1;
      }

      // Jika tidak ada data atau hanya 1 halaman, sembunyikan pagination
      if (currentTotalPages <= 1) {
        paginationList.style.display = "none";
        return false;
      }

      paginationList.style.display = "flex";

      // Tombol Previous
      const prevLi = document.createElement("li");
      prevLi.classList.add("page-item");
      if (currentPage === 1) prevLi.classList.add("disabled");
      prevLi.innerHTML = `<button class="page-link" data-page="${
        currentPage - 1
      }">Previous</button>`;
      paginationList.appendChild(prevLi);

      // Logika untuk menampilkan nomor halaman
      let startPage, endPage;
      if (currentTotalPages <= 5) {
        startPage = 1;
        endPage = currentTotalPages;
      } else {
        if (currentPage <= 3) {
          startPage = 1;
          endPage = 5;
        } else if (currentPage >= currentTotalPages - 2) {
          startPage = currentTotalPages - 4;
          endPage = currentTotalPages;
        } else {
          startPage = currentPage - 2;
          endPage = currentPage + 2;
        }
      }

      // Tambah fungsi helper untuk mendapatkan data yang sudah difilter
      function getFilteredData() {
        let filteredData = [...data[rowID]];

        // Terapkan filter berdasarkan filterBy yang aktif
        if (row.hasOwnProperty("filterBy")) {
          const filterBy = Array.isArray(row.filterBy)
            ? row.filterBy
            : [row.filterBy];

          filterBy.forEach((filterType) => {
            const filterElement = document.getElementById(filterType);
            if (filterElement && filterElement.value !== "all") {
              filteredData = filteredData.filter(
                (item) =>
                  String(item[filterType]) === String(filterElement.value)
              );
            }
          });
        }

        return filteredData;
      }

      // Modifikasi curPage untuk menggunakan data yang sudah difilter
      function curPage(page = 1) {
        const filteredData = getFilteredData();
        const startIndex = (page - 1) * pageLimit;
        const slicedData = filteredData.slice(
          startIndex,
          startIndex + pageLimit
        );
        return { [rowID]: slicedData };
      }

      // Update event handler untuk filter
      function handleFilterChange() {
        currentPage = 1; // Reset ke halaman pertama saat filter berubah
        const filteredData = getFilteredData();
        renderData(curPage(currentPage));
        updatePaginationUI(); // Update pagination setelah filter
      }

      // Setup filter event listeners
      if (row.hasOwnProperty("filterBy")) {
        const filterBy = Array.isArray(row.filterBy)
          ? row.filterBy
          : [row.filterBy];
        filterBy.forEach((filterType) => {
          const filterElement = document.getElementById(filterType);
          if (filterElement) {
            filterElement.removeEventListener("change", handleFilterChange);
            filterElement.addEventListener("change", handleFilterChange);
          }
        });
      }

      // Render nomor halaman
      for (let i = startPage; i <= endPage; i++) {
        const pageLi = document.createElement("li");
        pageLi.classList.add("page-item");
        if (i === currentPage) pageLi.classList.add("active");
        pageLi.innerHTML = `<button class="page-link" data-page="${i}">${i}</button>`;
        paginationList.appendChild(pageLi);
      }

      // Tombol Next
      const nextLi = document.createElement("li");
      nextLi.classList.add("page-item");
      if (currentPage === currentTotalPages) nextLi.classList.add("disabled");
      nextLi.innerHTML = `<button class="page-link" data-page="${
        currentPage + 1
      }">Next</button>`;
      paginationList.appendChild(nextLi);
    }

    /**
     * Setup event listeners untuk pagination
     */
    function setupPaginationListeners() {
      // Cek apakah pagination didefinisikan dan tidak false
      if (!row.hasOwnProperty("pagination") || row.pagination === false) {
        return false;
      }

      const paginationID = row.pagination;
      if (!paginationID) return false;

      const paginationList = document.getElementById(paginationID);
      if (!paginationList) return false;

      paginationList.addEventListener("click", function (event) {
        const button = event.target.closest(".page-link");
        if (!button) return;

        const newPage = parseInt(button.dataset.page);

        // Validasi halaman
        if (isNaN(newPage) || newPage < 1 || newPage > totalPages) return;
        if (newPage === currentPage) return;

        currentPage = newPage;
        renderData(curPage(currentPage));
        updatePaginationUI();
      });
    }

    // Initial setup
    setupPaginationListeners();

    /**
     * Fungsi untuk memuat ulang data
     * @param {Object|Array} newData - Data baru yang akan dimuat
     * @param {boolean} resetPage - Reset ke halaman pertama (default: true)
     */
    View.prototype.addData = function (newData, resetPage = true) {
      if (
        !newData ||
        (typeof newData !== "object" && !Array.isArray(newData))
      ) {
        throw new Error("Parameter newData harus berupa object atau array");
      }

      try {
        // Backup data lama untuk rollback jika terjadi error
        const oldData = { ...data };
        const oldOriginalData = { ...originalData };

        let newItems = [];

        // Validasi dan ekstrak data baru
        if (Array.isArray(newData)) {
          newItems = [...newData];
        } else {
          const firstKey = Object.keys(newData)[0];
          if (!firstKey || !Array.isArray(newData[firstKey])) {
            throw new Error("Data harus berupa array atau object dengan array");
          }
          newItems = [...newData[firstKey]];
        }

        // Validasi data baru tidak kosong
        if (newItems.length === 0) {
          throw new Error("Data baru tidak boleh kosong");
        }

        try {
          // Proses data baru dengan slug
          const processedNewItems = processDataWithSlug(newItems);

          // Gabungkan data baru di awal dengan data lama
          originalData[rowID] = [...newItems, ...originalData[rowID]];
          data[rowID] = [...processedNewItems, ...data[rowID]];
        } catch (slugError) {
          console.warn("Error saat memproses slug:", slugError);
          // Jika gagal memproses slug, tetap gabungkan data tanpa slug
          originalData[rowID] = [...newItems, ...originalData[rowID]];
          data[rowID] = [...newItems, ...data[rowID]];
        }

        // Update cache dan perhitungan terkait
        cachedData.totalPages = Math.ceil(data[rowID].length / pageLimit);

        // Reset pencarian jika ada
        if (cachedData.searchInput) {
          cachedData.searchInput.value = "";
        }

        // Reset ke halaman pertama jika diminta
        if (resetPage) {
          currentPage = 1;
        }

        // Clear memoization cache karena data berubah
        if (memoizedFilter && memoizedFilter.cache) {
          memoizedFilter.cache.clear();
        }

        // Render ulang dengan data baru
        requestAnimationFrame(() => {
          renderData(curPage(currentPage));

          // Trigger custom event untuk notifikasi reload selesai
          const reloadEvent = new CustomEvent("dataReloaded", {
            detail: {
              success: true,
              newItemsCount: newItems.length,
              totalItems: data[rowID].length,
              currentPage: currentPage,
            },
          });
          document.dispatchEvent(reloadEvent);
        });

        return true;
      } catch (error) {
        console.error("Error saat reload data:", error);

        // Rollback ke data lama jika terjadi error
        data = { ...oldData };
        originalData = { ...oldOriginalData };

        // Trigger custom event untuk notifikasi error
        const errorEvent = new CustomEvent("dataReloadError", {
          detail: {
            error: error.message,
          },
        });
        document.dispatchEvent(errorEvent);

        return false;
      }
    };

    /**
     * Fungsi untuk mendapatkan data saat ini
     * @returns {Object} Data yang sedang ditampilkan
     */
    View.prototype.getCurrentData = function () {
      return {
        all: data[rowID],
        current: curPage(currentPage)[rowID],
        pagination: {
          currentPage: currentPage,
          totalPages: cachedData.totalPages,
          pageLimit: pageLimit,
          totalItems: data[rowID].length,
        },
      };
    };

    /**
     * Fungsi untuk refresh manual dengan tombol
     * @param {string} buttonId - ID tombol refresh
     * @param {Object} options - Data dan opsi refresh
     */
    View.prototype.setupRefreshButton = function (buttonId, options = {}) {
      const refreshButton = document.getElementById(buttonId);
      if (!refreshButton) {
        console.warn("Tombol refresh tidak ditemukan:", buttonId);
        return null;
      }

      const {
        onStart,
        onSuccess,
        onError,
        loadingText = "Memperbarui...",
        data = null,
        reloadOptions = {}
      } = options;

      let isLoading = false;
      const originalText = refreshButton.innerHTML;

      const handleRefresh = async () => {
        if (isLoading) return;

        try {
          isLoading = true;
          refreshButton.disabled = true;
          refreshButton.innerHTML = loadingText;

          if (onStart) await onStart();

          if (data) {
            const success = this.ReloadView(data, reloadOptions);
            if (success && onSuccess) {
              await onSuccess({ data });
            }
          }

        } catch (error) {
          console.error("Error saat refresh:", error);
          if (onError) await onError(error);
        } finally {
          isLoading = false;
          refreshButton.disabled = false;
          refreshButton.innerHTML = originalText;
        }
      };

      const cleanup = () => {
        refreshButton.removeEventListener("click", handleRefresh);
      };

      refreshButton.addEventListener("click", handleRefresh);
      return cleanup;
    };

    /**
     * Fungsi untuk refresh dengan onclick
     * @param {Object} data - Data untuk refresh
     */
    View.prototype.ReloadView = function (newData, options = {}) {
      try {
        // Validasi data dengan lebih fleksibel
        let dataToLoad;
        if (Array.isArray(newData)) {
          dataToLoad = { [rowID]: newData };
        } else if (newData && newData.data && newData.data[rowID]) {
          dataToLoad = { [rowID]: newData.data[rowID] };
        } else if (newData && newData[rowID]) {
          dataToLoad = newData;
        } else {
          throw new Error('Format data tidak valid untuk reload');
        }

        const {
          append = false,
          preserveFilters = false,
          resetPage = true
        } = options;

        // Backup data lama untuk rollback
        const oldData = [...data[rowID]];
        const oldOriginalData = [...originalData[rowID]];

        try {
          if (append) {
            // Tambahkan data baru ke existing data
            originalData[rowID] = [...originalData[rowID], ...dataToLoad[rowID]];
            data[rowID] = [...data[rowID], ...dataToLoad[rowID]];
          } else {
            // Ganti dengan data baru
            originalData[rowID] = [...dataToLoad[rowID]];
            data[rowID] = [...dataToLoad[rowID]];
          }

          // Reset ke halaman pertama jika diminta
          if (resetPage) {
            currentPage = 1;
          }

          // Update UI menggunakan TDSDOM
          const rendered = domManager.render(template, curPage(currentPage), templateElement);
          contentElement.innerHTML = rendered;
          updatePaginationUI();

          return {
            success: true,
            totalItems: data[rowID].length,
            currentPage: currentPage
          };

        } catch (error) {
          // Rollback jika terjadi error
          data[rowID] = oldData;
          originalData[rowID] = oldOriginalData;
          throw error;
        }

      } catch (error) {
        console.error('Error dalam ReloadView:', error);
        return {
          success: false,
          error: error.message
        };
      }
    };

    function renderLargeTemplate(template, data) {
      const chunkSize = 1000; // karakter
      const chunks = [];

      for (let i = 0; i < template.length; i += chunkSize) {
        chunks.push(template.slice(i, i + chunkSize));
      }

      let result = "";
      chunks.forEach((chunk, index) => {
        requestAnimationFrame(() => {
          result += processTemplateChunk(chunk, data);
          if (index === chunks.length - 1) {
            contentElement.innerHTML = result;
          }
        });
      });
    }

    /**
     * Setup lazy loading untuk gambar dan konten
     */
    function setupLazyLoading() {
      const options = {
        root: null,
        rootMargin: "50px",
        threshold: 0.1,
      };

      // Observer untuk gambar
      const imageObserver = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) {
            const img = entry.target;
            if (img.dataset.src) {
              // Load gambar dengan fade effect
              img.style.opacity = "0";
              img.src = img.dataset.src;
              img.onload = () => {
                img.style.transition = "opacity 0.3s";
                img.style.opacity = "1";
              };
              delete img.dataset.src;
              imageObserver.unobserve(img);
            }
          }
        });
      }, options);

      // Observer untuk konten berat
      const contentObserver = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) {
            const element = entry.target;
            if (element.dataset.content) {
              loadHeavyContent(element);
              contentObserver.unobserve(element);
            }
          }
        });
      }, options);

      // Load konten berat
      function loadHeavyContent(element) {
        const contentId = element.dataset.content;

        // Gunakan worker untuk load konten berat
        if (worker) {
          worker.postMessage({
            action: "loadContent",
            contentId: contentId,
          });
        }
      }

      // Observe semua gambar lazy
      document.querySelectorAll("img[data-src]").forEach((img) => {
        imageObserver.observe(img);
      });

      // Observe konten berat
      document.querySelectorAll("[data-content]").forEach((element) => {
        contentObserver.observe(element);
      });

      return {
        imageObserver,
        contentObserver,
      };
    }

    // Cleanup untuk lazy loading
    this.destroy = function () {
      if (this.lazyLoadObservers) {
        this.lazyLoadObservers.imageObserver.disconnect();
        this.lazyLoadObservers.contentObserver.disconnect();
      }
      // ... cleanup lainnya
    };

    // Setup lazy loading saat inisialisasi
    this.lazyLoadObservers = setupLazyLoading();

    // Modifikasi destroy untuk membersihkan event listeners
    this.destroy = function () {
      if (worker) {
        worker.terminate();
        worker = null;
      }

      // Cleanup filter elements
      if (cachedData.filterElements) {
        cachedData.filterElements.forEach(({ element, handler }) => {
          element.removeEventListener("change", handler);
        });
      }

      // Cleanup lazy loading observers
      if (this.lazyLoadObservers) {
        this.lazyLoadObservers.imageObserver.disconnect();
        this.lazyLoadObservers.contentObserver.disconnect();
      }
    };

    /**
     * Setup virtual scrolling untuk data besar
     */
    function setupVirtualScrolling() {
      const viewportHeight = window.innerHeight;
      const itemHeight = 50; // Perkiraan tinggi setiap item
      const bufferSize = 5; // Jumlah item buffer atas dan bawah
      const visibleItems =
        Math.ceil(viewportHeight / itemHeight) + bufferSize * 2;

      let startIndex = 0;
      let scrollTimeout;

      const container = contentElement;
      const scrollContainer = document.createElement("div");
      scrollContainer.style.position = "relative";
      container.appendChild(scrollContainer);

      function updateVisibleItems() {
        const scrollTop = container.scrollTop;
        startIndex = Math.floor(scrollTop / itemHeight);
        startIndex = Math.max(0, startIndex - bufferSize);

        const visibleData = data[rowID].slice(
          startIndex,
          startIndex + visibleItems
        );
        const totalHeight = data[rowID].length * itemHeight;

        scrollContainer.style.height = `${totalHeight}px`;

        // Render hanya item yang visible
        const fragment = document.createDocumentFragment();
        visibleData.forEach((item, index) => {
          const itemElement = document.createElement("div");
          itemElement.style.position = "absolute";
          itemElement.style.top = `${(startIndex + index) * itemHeight}px`;
          itemElement.style.height = `${itemHeight}px`;

          const rendered = optimizedRender({ [rowID]: [item] }, rowID);
          itemElement.innerHTML = rendered;

          fragment.appendChild(itemElement);
        });

        // Clear dan update content
        while (scrollContainer.firstChild) {
          scrollContainer.removeChild(scrollContainer.firstChild);
        }
        scrollContainer.appendChild(fragment);
      }

      container.addEventListener("scroll", () => {
        if (scrollTimeout) {
          cancelAnimationFrame(scrollTimeout);
        }
        scrollTimeout = requestAnimationFrame(updateVisibleItems);
      });

      // Initial render
      updateVisibleItems();

      return {
        refresh: updateVisibleItems,
        destroy: () => {
          container.removeEventListener("scroll", updateVisibleItems);
          scrollContainer.remove();
        },
      };
    }

    /**
     * Setup data chunking dan storage
     */
    class DataChunkManager {
      constructor(dbName = "viewDB", storeName = "chunks") {
        this.dbName = dbName;
        this.storeName = storeName;
        this.chunkSize = 1000; // Items per chunk
        this.db = null;
      }

      async init() {
        return new Promise((resolve, reject) => {
          const request = indexedDB.open(this.dbName, 1);

          request.onerror = () => reject(request.error);
          request.onsuccess = () => {
            this.db = request.result;
            resolve();
          };

          request.onupgradeneeded = (event) => {
            const db = event.target.result;
            if (!db.objectStoreNames.contains(this.storeName)) {
              db.createObjectStore(this.storeName, { keyPath: "chunkId" });
            }
          };
        });
      }

      async storeChunks(data) {
        const chunks = this.createChunks(data);
        const store = this.db
          .transaction(this.storeName, "readwrite")
          .objectStore(this.storeName);

        return Promise.all(
          chunks.map(
            (chunk) =>
              new Promise((resolve, reject) => {
                const request = store.put(chunk);
                request.onsuccess = () => resolve();
                request.onerror = () => reject(request.error);
              })
          )
        );
      }

      async getChunk(chunkId) {
        return new Promise((resolve, reject) => {
          const request = this.db
            .transaction(this.storeName)
            .objectStore(this.storeName)
            .get(chunkId);

          request.onsuccess = () => resolve(request.result);
          request.onerror = () => reject(request.error);
        });
      }

      createChunks(data) {
        const chunks = [];
        for (let i = 0; i < data.length; i += this.chunkSize) {
          chunks.push({
            chunkId: Math.floor(i / this.chunkSize),
            data: data.slice(i, i + this.chunkSize),
          });
        }
        return chunks;
      }
    }

    /**
     * Setup data streaming untuk load data besar
     */
    class DataStreamManager {
      constructor(options = {}) {
        this.pageSize = options.pageSize || 50;
        this.worker = options.worker;
        this.chunkManager = new DataChunkManager();
      }

      async init() {
        await this.chunkManager.init();
        this.setupStreamHandlers();
      }

      setupStreamHandlers() {
        let currentChunk = 0;
        let isLoading = false;

        const loadNextChunk = async () => {
          if (isLoading) return;
          isLoading = true;

          try {
            const chunk = await this.chunkManager.getChunk(currentChunk);
            if (chunk) {
              // Process chunk dengan worker
              this.worker.postMessage({
                action: "processChunk",
                data: chunk.data,
              });
              currentChunk++;
            }
          } catch (error) {
            console.error("Error loading chunk:", error);
          } finally {
            isLoading = false;
          }
        };

        // Setup intersection observer untuk infinite scroll
        const observer = new IntersectionObserver(
          (entries) => {
            if (entries[0].isIntersecting) {
              loadNextChunk();
            }
          },
          { threshold: 0.5 }
        );

        // Observe loader element
        const loader = document.querySelector("#chunk-loader");
        if (loader) {
          observer.observe(loader);
        }
      }

      async processStreamedData(data) {
        // Store chunks di IndexedDB
        await this.chunkManager.storeChunks(data);

        // Setup virtual scrolling
        const virtualScroller = setupVirtualScrolling();

        return {
          destroy: () => {
            virtualScroller.destroy();
            // Cleanup lainnya
          },
        };
      }
    }

    // Inisialisasi managers
    const streamManager = new DataStreamManager({ worker });
    let virtualScroller = null;

    async function initializeDataHandling() {
      await streamManager.init();

      if (data[rowID].length > 1000) {
        // Gunakan virtual scrolling untuk data besar
        virtualScroller = setupVirtualScrolling();

        // Process data dengan streaming
        await streamManager.processStreamedData(data[rowID]);
      } else {
        // Render normal untuk data kecil
        renderData(curPage(1));
      }
    }

    // Modify destroy method
    this.destroy = function () {
      if (virtualScroller) {
        virtualScroller.destroy();
      }
      if (worker) {
        worker.terminate();
      }
      // ... cleanup lainnya
    };

    // Initialize
    initializeDataHandling().catch(console.error);

    /**
     * Filter data berdasarkan key dan value
     */
    this.filterKey = function (key, value) {
      if (!key || !value) {
        console.warn("Parameter key dan value harus diisi");
        return this;
      }

      try {
        // Filter data berdasarkan key dan value
        data[rowID] = originalData[rowID].filter(item => {
          return String(item[key]) === String(value);
        });

        // Update UI
        currentPage = 1;
        renderData(curPage(1));
        updatePaginationUI();

        // Return object dengan informasi hasil filter
        return {
          filtered: data[rowID].length,
          total: originalData[rowID].length,
          data: data[rowID]
        };

      } catch (error) {
        console.error('Error dalam filterKey:', error);
        return {
          filtered: 0,
          total: originalData[rowID].length,
          data: []
        };
      }
    };

    /**
     * Internal filter state management
     */
    this._filterState = {
      active: {},
      history: [],
      
      add: function(key, value) {
        this.active[key] = value;
        this.history.push({ 
          key, 
          value, 
          timestamp: Date.now() 
        });
      },
      
      remove: function(key) {
        delete this.active[key];
      },
      
      clear: function() {
        this.active = {};
        this.history = [];
      },
      
      get: function(key) {
        return this.active[key];
      },
      
      getAll: function() {
        return {...this.active};
      }
    };

    /**
     * Internal filter helper
     */
    this._internalFilter = function(key, value) {
      if (!key || !value) return;

      try {
        data[rowID] = data[rowID].filter((item) => {
          // Handle nested object
          if (key.includes('.')) {
            const keys = key.split('.');
            let val = item;
            for (const k of keys) {
              if (val === undefined) return false;
              val = val[k];
            }
            return String(val) === String(value);
          }
          
          // Handle array value
          if (Array.isArray(value)) {
            return value.includes(String(item[key]));
          }
          
          return String(item[key]) === String(value);
        });

      } catch (error) {
        console.error('Error pada internal filter:', error);
      }
    };

    /**
     * Method untuk mengubah urutan data
     * @param {string} order - 'ASC' atau 'DESC'
     * @param {string} sortBy - field yang akan diurutkan
     */
    View.prototype.sort = function(order, sortBy) {
      data[rowID] = sortData(data[rowID], order, sortBy);
      currentPage = 1;
      renderData(curPage(1));
      updatePaginationUI();
    };
  }
}

// Export class-class tambahan jika diperlukan
export { View };




// Export instance WebSocket default
export const tatiye = createWebSocketConnection(); 

export function RTDb(callback,token) {
    let pesanData;
    tatiye.onopen = function() {
      const subscribeMsg = {
        type: 'subscribe',
        endpoint:token
      };
      tatiye.send(JSON.stringify(subscribeMsg));
    };
    tatiye.onmessage = function(event) {
     const data = JSON.parse(event.data);
     if (data.type === 'update') {
       pesanData = data.data.response;
       callback(pesanData);
     }
    };

    tatiye.onerror = function(error) {
      console.error('WebSocket error:', error);
    };

    tatiye.onclose = function() {
      console.log('Terputus dari WebSocket server');
      setTimeout(RTDb, 5000);
    };
}
// ... existing connection code ...
export function Buckets(data = {}) {
  return new Promise((resolve, reject) => {
    if (tatiye.readyState === WebSocket.OPEN) {
      const apiRequest = {
        type: 'apiRequest',
        endpoint: data.endpoint,
        vid:app.vid,
        payload: data.body
      };
      const messageHandler = (e) => {
        try {
          const response = JSON.parse(e.data);
          if (response.type === "apiResponse") {
               tatiye.removeEventListener("message", messageHandler);
               if (response.data.payload.vid===app.vid) {
                  resolve(response.data.payload.response);
               }
          }
        } catch (error) {
          console.error('Error dalam messageHandler:', error);
          reject(error);
        }
      };
      tatiye.addEventListener("message", messageHandler);
      tatiye.send(JSON.stringify(apiRequest));
    } else {
      console.error('WebSocket Status:', tatiye.readyState);
      reject(new Error("WebSocket belum terhubung"));
    }
  });
}

// filebrowser
export async function filebrowser(serverUrl,fileInput, additionalData = {}) {
  const formdata = new FormData();
  formdata.append("file", fileInput.files[0]);
  
  // Menambahkan data tambahan secara dinamis
  Object.entries(additionalData).forEach(([key, value]) => {
    formdata.append(key, value);
  });

  const requestOptions = {
    method: "POST", 
    body: formdata,
    redirect: "follow"
  };

  try {
    const response = await fetch(app.url+"/sdk/"+serverUrl, requestOptions);
    const result = await response.json();
    return result;
  } catch (error) {
    console.error("Error uploading file:", error);
    throw error;
  }
}
// Form Wizard
export function createWizard(options = {}) {
  const {
    framework = "ngorei",
    data = {},
    width = "100%",
    formBackground = "#ffffff",
  } = options;

  // Tambahkan variabel untuk menyimpan instance wizard
  let wizardInstance = null;

  // Definisikan mountForm di awal
  function mountForm(containerId) {
    const container = document.getElementById(containerId);
    if (!container) {
      console.error(`Container dengan ID ${containerId} tidak ditemukan`);
      return;
    }

    // Set container style
    container.style.width = options.width;
    container.style.backgroundColor = options.formBackground;
    const formContent = generateFormFields(options.data.action);
    container.innerHTML = formContent;
    // Setup semua handler
    setupFormValidation(container);
    setupFileHandlers(container);
    setupDatepicker(container);
    // Simpan instance wizard
    wizardInstance = setupWizard(container);

    return {
      setActiveSteps: wizardInstance.setActiveSteps,
      resetForm: () => {
        const form = container.querySelector("form");
        if (form) {
          resetForm(form);
        }
      },
    };
  }

  // Fungsi untuk membuat formulir
  function generateFormFields(actions) {
    // Tambahkan container untuk wizard
    let formHTML = `
            <div class="container-fluid">
                <div class="wizard-container">
                    <div class="wizard-steps-wrapper">
                        <ul class="wizard-steps">
                            ${options.data.steps
                              .map(
                                (step, index) => `
                                <li class="wizard-step ${
                                  index === 0 ? "active" : ""
                                }" data-step="${index}">
                                    <div class="ngrstep-number">${
                                      index + 1
                                    }</div>
                                    <div class="step-content">
                                        <span class="step-title">${
                                          step.title
                                        }</span>
                                        <span class="step-line"></span>
                                    </div>
                                </li>
                            `
                              )
                              .join("")}
                        </ul>
                    </div>
                    
                    <form class="fluid">
                        ${options.data.steps
                          .map(
                            (step, index) => `
                            <div class="wizard-content" data-step="${index}" ${
                              index === 0 ? "" : 'style="display:none"'
                            }>
                                <div class="row row-xs">
                                    ${generateStepFields(step.fields, actions)}
                                </div>
                            </div>
                        `
                          )
                          .join("")}
                        
                        <div class="wizard-footer">
                            <button type="button" class="btn ${
                              options.data.footer.prev[1]
                            } btn-prev" style="display:none">${
      options.data.footer.prev[0]
    }</button>
                            <button type="button" class="btn ${
                              options.data.footer.next[1]
                            } btn-next">${options.data.footer.next[0]}</button>
                            <button type="submit" class="btn ${
                              options.data.footer.save[1]
                            } btn-submit" style="display:none">${
      options.data.footer.save[0]
    }</button>
                        </div>
                    </form>
                </div>
            </div>`;

    // Tambahkan style untuk wizard steps
    formHTML += `
      <style>
        .ngrstep-number {
          width: 40px;
          height: 40px;
          line-height: 30px;
          border-radius: 50%;
          background: ${
            options.data.stepNumber?.backgrounddefault || "#e9ecef"
          };
          color: #666;
          margin: 0 auto 5px;
          font-size: ${options.data.stepNumber?.fontSize || "16px"};
          border: ${options.data.stepNumber?.border || "1px solid #ccc"};
          padding: ${options.data.stepNumber?.padding || "5px"};
        }
        
        .wizard-step.active .ngrstep-number {
          background: ${options.data.stepNumber?.backgroundActiv || "#007bff"};
          color: ${options.data.stepNumber?.colorText || "#ffffff"};
        }
   
        
        .wizard-step.active .step-title {
          color: ${options.data.stepNumber?.backgroundActiv || "#007bff"};
          font-weight: bold;
        }
      </style>
    `;

    // Tambahkan style untuk posisi footer
    const footerPosition = options.data.footer?.position || "right";
    formHTML += `
      <style>
        .wizard-footer {
          display: flex;
          gap: 10px;
          justify-content: ${
            footerPosition === "left"
              ? "flex-start"
              : footerPosition === "center"
              ? "center"
              : "flex-end"
          };
        }
      </style>
    `;

    return formHTML;
  }

  // Fungsi helper untuk generate fields per step
  function generateStepFields(fields, actions) {
    let stepHTML = "";

    fields.forEach((fieldName) => {
      if (actions[fieldName]) {
        const [
          type,
          size,
          label,
          placeholder,
          iconPosition,
          iconClass,
          validation,
        ] = actions[fieldName];
        const inputId = type === "datepicker" ? "datepicker" : fieldName;
        const defaultValue = options.data.defaultData?.[fieldName] || "";

        stepHTML += `<div class="col-${size}"><div class="form-group">`;
        stepHTML += `<label for="${fieldName}">${label}</label>`;

        // Handle berbagai tipe input
        switch (type) {
          case "password":
            stepHTML += `
                        <div class="input-wrapper ${iconPosition}-icon">
                            <i class="${iconClass}" style="position: absolute; ${iconPosition}: 10px; top: 50%; transform: translateY(-50%);"></i>
                            <input type="password" 
                                id="${inputId}" 
                                name="${fieldName}" 
                                class="form-control password-input" 
                                placeholder="${placeholder}"
                                required 
                                minlength="${validation}"
                                data-validation="true"
                                value="${defaultValue}"
                                autocomplete="current-password"
                                style="padding-${iconPosition}: 35px; padding-right: 40px;">
                            <button type="button" class="toggle-password">
                                <i class="fa-solid fa-eye"></i>
                            </button>
                        </div>`;
            break;
          case "text":
          case "email":
          case "tel":
            const hasIcon =
              iconClass &&
              iconClass !== false &&
              iconPosition &&
              iconPosition !== false;
            let iconStyles = "";
            let inputStyles = "";
            let wrapperClass = "input-wrapper";

            if (hasIcon) {
              wrapperClass += ` has-icon icon-${iconPosition}`;
              if (iconPosition === "right") {
                iconStyles =
                  "position: absolute; right: 10px; top: 50%; transform: translateY(-50%);";
                inputStyles = "padding-right: 35px; padding-left: 12px;";
              } else {
                iconStyles =
                  "position: absolute; left: 10px; top: 50%; transform: translateY(-50%);";
                inputStyles = "padding-left: 35px; padding-right: 12px;";
              }
            } else {
              inputStyles = "padding: 0.375rem 12px;";
            }

            stepHTML += `
              <div class="${wrapperClass}" style="position: relative;">
                  ${
                    hasIcon
                      ? `<i class="${iconClass}" style="${iconStyles}"></i>`
                      : ""
                  }
                  <input type="${type}" 
                      id="${inputId}" 
                      name="${fieldName}"
                      class="form-control" 
                      placeholder="${placeholder}"
                      ${
                        type === "email"
                          ? 'pattern="[^@\\s]+@[^@\\s]+\\.[^@\\s]+"'
                          : ""
                      }
                      minlength="${validation}"
                      data-validation="true"
                      value="${defaultValue}"
                      style="${inputStyles} -webkit-appearance: none;"
                      autocomplete="off"
                      tabindex="0">
              </div>
              <div class="error-message" style="display: none; color: red; font-size: 12px; margin-top: 5px;">
                  ${label} harus diisi minimal ${validation} karakter
              </div>`;
            break;

          case "richtext":
            stepHTML += `
                        <div class="richtext-wrapper">
                            <div id="${inputId}" class="richtext-editor" data-validation="true">
                                <div class="richtext-toolbar">
                                    ${validation.toolbar
                                      .map(
                                        (tool) => `
                                        <button type="button" 
                                            class="toolbar-btn" 
                                            data-command="${tool.command}"
                                            title="${
                                              tool.command
                                                .charAt(0)
                                                .toUpperCase() +
                                              tool.command.slice(1)
                                            }">
                                            <i class="fas ${tool.icon}"></i>
                                        </button>
                                    `
                                      )
                                      .join("")}
                                </div>
                                <div class="richtext-content" 
                                    contenteditable="true" 
                                    data-placeholder="${placeholder}"
                                    style="height: ${validation.height};"
                                    minlength="${validation.minLength || 10}">
                                    ${defaultValue}
                                </div>
                                <div class="error-message" style="display: none; color: red; font-size: 12px; margin-top: 5px;"></div>
                                <input type="hidden" name="${fieldName}" value="${defaultValue}">
                            </div>
                        </div>`;

            // Tambahkan event listener untuk toolbar
            setTimeout(() => {
              const editor = document.getElementById(inputId);
              const toolbar = editor.querySelector(".richtext-toolbar");
              const content = editor.querySelector(".richtext-content");
              const hiddenInput = editor.querySelector('input[type="hidden"]');

              // Update hidden input saat konten berubah
              content.addEventListener("input", () => {
                hiddenInput.value = content.innerHTML;
              });

              // Handle toolbar clicks
              toolbar.addEventListener("click", (e) => {
                const button = e.target.closest(".toolbar-btn");
                if (!button) return;

                e.preventDefault();
                const command = button.dataset.command;

                // Eksekusi perintah formatting
                document.execCommand(command, false, null);

                // Focus kembali ke editor
                content.focus();
              });
            }, 0);
            break;

          case "range":
            const rangeValue = defaultValue || validation.default;
            stepHTML += `
                        <div class="range-wrapper">
                            <input type="range" 
                                id="${inputId}" 
                                name="${fieldName}"
                                min="${validation.min}"
                                max="${validation.max}"
                                step="${validation.step}"
                                value="${rangeValue}"
                                class="range-input">
                            ${
                              validation.showValue
                                ? `<output class="range-value">${rangeValue}</output>`
                                : ""
                            }
                        </div>`;
            break;

          case "color":
            stepHTML += `
                        <div class="color-picker-wrapper">
                            <input type="color" 
                                id="${inputId}" 
                                name="${fieldName}"
                                value="${
                                  defaultValue || validation.defaultColor
                                }"
                                class="color-input">
                            ${
                              validation.showPalette
                                ? `
                                <div class="color-palette">
                                    <button type="button" data-color="#ff0000"></button>
                                    <button type="button" data-color="#00ff00"></button>
                                    <button type="button" data-color="#0000ff"></button>
                                </div>
                            `
                                : ""
                            }
                        </div>`;
            break;

          case "radio":
            stepHTML += `<div class="radio-group">`;
            validation.forEach((option) => {
              const isChecked = defaultValue === option.value ? "checked" : "";
              stepHTML += `
                            <div class="form-check">
                                <input type="radio" id="${fieldName}_${option.value}"
                                    name="${fieldName}" value="${option.value}" 
                                    class="form-check-input" ${isChecked}>
                                <label class="form-check-label" for="${fieldName}_${option.value}">
                                    ${option.label}
                                </label>
                            </div>`;
            });
            stepHTML += `</div>`;
            break;

          case "select":
            stepHTML += `
                        <div class="input-wrapper ${iconPosition}-icon">
                            <i class="${iconClass}" style="position: absolute; ${iconPosition}: 10px; top: 50%; transform: translateY(-50%);"></i>
                            <select id="${inputId}" 
                                name="${fieldName}" 
                                class="form-control"
                                data-validation="true"
                                minlength="1"
                                style="padding-${iconPosition}: 35px;">
                                <option value="">${placeholder}</option>
                                ${validation
                                  .map(
                                    (option) => `
                                    <option value="${option.value}" ${
                                      defaultValue === option.value
                                        ? "selected"
                                        : ""
                                    }>
                                        ${option.label}
                                    </option>`
                                  )
                                  .join("")}
                            </select>
                        </div>`;
            break;

          case "checkbox":
            stepHTML += `<div class="checkbox-group" data-validation="true">`;
            validation.forEach((option) => {
              const isChecked =
                Array.isArray(defaultValue) &&
                defaultValue.includes(option.value)
                  ? "checked"
                  : "";
              stepHTML += `
                            <div class="form-check">
                                <input type="checkbox" id="${fieldName}_${option.value}"
                                    name="${fieldName}" value="${option.value}" 
                                    class="form-check-input" ${isChecked}>
                                <label class="form-check-label" for="${fieldName}_${option.value}">
                                    ${option.label}
                                </label>
                            </div>`;
            });
            stepHTML += `</div>`;
            break;

          case "search":
            const defaultSearchValue = defaultValue || "";
            const defaultSearchItem = validation.find(
              (item) => item.value === defaultSearchValue
            );
            const defaultSearchLabel = defaultSearchItem
              ? defaultSearchItem.label
              : "";

            const hasSearchIcon =
              iconClass &&
              iconClass !== false &&
              iconPosition &&
              iconPosition !== false;
            let searchIconStyles = "";
            let searchInputStyles = "";
            let searchWrapperClass = "input-wrapper";

            if (hasSearchIcon) {
              searchWrapperClass += ` has-icon icon-${iconPosition}`;
              if (iconPosition === "right") {
                searchIconStyles =
                  "position: absolute; right: 10px; top: 50%; transform: translateY(-50%);";
                searchInputStyles = "padding-right: 35px; padding-left: 12px;";
              } else {
                searchIconStyles =
                  "position: absolute; left: 10px; top: 50%; transform: translateY(-50%);";
                searchInputStyles = "padding-left: 35px; padding-right: 12px;";
              }
            } else {
              searchInputStyles = "padding: 0.375rem 12px;";
            }

            stepHTML += `
              <div class="${searchWrapperClass}" style="position: relative;">
                  ${
                    hasSearchIcon
                      ? `<i class="${iconClass}" style="${searchIconStyles}"></i>`
                      : ""
                  }
                  <input type="text" 
                      id="${inputId}" 
                      name="${fieldName}"
                      class="form-control search-input" 
                      placeholder="${placeholder || "Cari..."}"
                      autocomplete="off"
                      value="${defaultSearchLabel}"
                      data-value="${defaultSearchValue}"
                      style="${searchInputStyles} -webkit-appearance: none;">
                  <div class="search-results" style="display: none;">
                      <ul class="search-list">
                          ${validation
                            .map(
                              (option) => `
                                  <li class="search-item" data-value="${option.value}">
                                      ${option.label}
                                  </li>
                              `
                            )
                            .join("")}
                      </ul>
                  </div>
              </div>`;
            break;

          case "multibox":
            const selectedValues = Array.isArray(defaultValue)
              ? defaultValue
              : [];
            stepHTML += `
                        <div class="input-wrapper ${iconPosition}-icon">
                            <i class="${iconClass}" style="position: absolute; ${iconPosition}: 10px; top: 50%; transform: translateY(-50%);"></i>
                            <input type="text" 
                                id="${inputId}" 
                                name="${fieldName}"
                                class="form-control multibox-input" 
                                placeholder="${
                                  placeholder || "Ketik untuk mencari..."
                                }"
                                autocomplete="off"
                                style="padding-${iconPosition}: 35px;">
                            <div class="selected-items">
                                ${selectedValues
                                  .map((value) => {
                                    const option = validation.find(
                                      (opt) => opt.value === value
                                    );
                                    return option
                                      ? `
                                        <span class="selected-tag" data-value="${value}">
                                            ${option.label}
                                            <button type="button" class="remove-tag" data-value="${value}">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </span>
                                      `
                                      : "";
                                  })
                                  .join("")}
                            </div>
                            <div class="multibox-results" style="display: none;">
                                <ul class="multibox-list">
                                    ${validation
                                      .map(
                                        (option) => `
                                        <li class="multibox-item" data-value="${
                                          option.value
                                        }">
                                            <label class="multibox-label">
                                                <input type="checkbox" class="multibox-checkbox" 
                                                    value="${option.value}"
                                                    ${
                                                      selectedValues.includes(
                                                        option.value
                                                      )
                                                        ? "checked"
                                                        : ""
                                                    }>
                                                ${option.label}
                                            </label>
                                        </li>
                                    `
                                      )
                                      .join("")}
                                </ul>
                            </div>
                        </div>`;
            break;

          case "datepicker":
            const hasDatepickerIcon =
              iconClass &&
              iconClass !== false &&
              iconPosition &&
              iconPosition !== false;
            let datepickerIconStyles = "";
            let datepickerInputStyles = "";
            let datepickerWrapperClass = "input-wrapper";

            if (hasDatepickerIcon) {
              datepickerWrapperClass += ` has-icon icon-${iconPosition}`;
              if (iconPosition === "right") {
                datepickerIconStyles =
                  "position: absolute; right: 10px; top: 50%; transform: translateY(-50%);";
                datepickerInputStyles =
                  "padding-right: 35px; padding-left: 12px;";
              } else {
                datepickerIconStyles =
                  "position: absolute; left: 10px; top: 50%; transform: translateY(-50%);";
                datepickerInputStyles =
                  "padding-left: 35px; padding-right: 12px;";
              }
            } else {
              datepickerInputStyles = "padding: 0.375rem 12px;";
            }

            stepHTML += `
              <div class="${datepickerWrapperClass}" style="position: relative;">
                  ${
                    hasDatepickerIcon
                      ? `<i class="${iconClass}" style="${datepickerIconStyles}"></i>`
                      : ""
                  }
                  <input type="text" 
                      id="${inputId}" 
                      name="${fieldName}"
                      class="form-control datepicker" 
                      placeholder="${placeholder}"
                      value="${defaultValue}"
                      data-validation="true"
                      minlength="1"
                      autocomplete="off"
                      readonly
                      style="${datepickerInputStyles} -webkit-appearance: none;">
              </div>`;
            break;

          case "file":
            const defaultFileUrl = defaultValue || "";
            stepHTML += `
                        <div class="input-wrapper ${iconPosition}-icon">
                            <div class="file-upload-wrapper">
                                <div class="file-upload-area">
                                    <input type="file" 
                                        id="${inputId}" 
                                        name="${fieldName}"
                                        class="form-control file-input" 
                                        accept="${validation.accept}"
                                        ${validation.multiple ? "multiple" : ""}
                                        data-max-size="${validation.maxSize}">
                                    <div class="file-upload-content">
                                        <i class="fas fa-cloud-upload-alt"></i>
                                        <p>Browse file to upload</p>
                                        <span class="file-support">Support: ${validation.accept
                                          .replace(/\./g, "")
                                          .toUpperCase()}</span>
                                    </div>
                                </div>
                                ${
                                  validation.preview
                                    ? `
                                    <div class="file-preview">
                                        ${
                                          defaultFileUrl
                                            ? `
                                            <div class="file-preview-item">
                                                <div class="file-content">
                                                    <img src="${defaultFileUrl}" alt="Preview" style="max-width: 100px; max-height: 100px;">
                                                    <div class="file-info">
                                                        <span class="file-name">Current File</span>
                                                    </div>
                                                </div>
                                                <input type="hidden" name="${fieldName}_current" value="${defaultFileUrl}">
                                            </div>
                                        `
                                            : ""
                                        }
                                    </div>
                                `
                                    : ""
                                }
                            </div>
                        </div>`;
            break;

          // Existing datepicker and text cases...
        }

        stepHTML += `
                <div class="error-message" style="display: none; color: red; font-size: 12px; margin-top: 5px;">
                    ${
                      type === "tel"
                        ? "Hanya boleh angka dan minimal " +
                          validation +
                          " digit"
                        : "Minimal " + validation + " karakter"
                    }
                </div>
            </div></div>`;
      }
    });

    return stepHTML;
  }

  // Tambahkan fungsi setupWizard
  function setupWizard(container) {
    // Reset current step di awal
    let currentStep = 0;

    const form = container.querySelector("form");
    const steps = container.querySelectorAll(".wizard-step");
    const contents = container.querySelectorAll(".wizard-content");
    const prevBtn = container.querySelector(".btn-prev");
    const nextBtn = container.querySelector(".btn-next");
    const submitBtn = container.querySelector(".btn-submit");

    // Tambahkan fungsi untuk mengaktifkan/menonaktifkan step
    function setStepEnabled(stepIndex, enabled) {
      const step = steps[stepIndex];
      if (enabled) {
        step.classList.remove("disabled");
        step.style.pointerEvents = "auto";
        step.style.opacity = "1";
      } else {
        step.classList.add("disabled");
        step.style.pointerEvents = "none";
        step.style.opacity = "0.5";
      }
    }

    // Tambahkan fungsi untuk mengatur step yang aktif
    function setActiveSteps(activeSteps) {
      steps.forEach((step, index) => {
        setStepEnabled(index, activeSteps.includes(index));
      });
    }

    // Fungsi untuk validasi step saat ini
    function validateCurrentStep() {
      const currentContent = contents[currentStep];
      const inputs = currentContent.querySelectorAll("[data-validation]");
      let isValid = true;

      inputs.forEach((input) => {
        if (!validateInput(input)) {
          isValid = false;
        }
      });

      return isValid;
    }

    // Fungsi untuk navigasi antar step
    function goToStep(step) {
      // Validasi step
      if (step < 0 || step >= steps.length || !steps[step]) {
        console.warn("Invalid step index:", step);
        return;
      }

      // Cek apakah step yang dituju dinonaktifkan
      if (steps[step].classList.contains("disabled")) {
        return;
      }

      // Validasi hanya jika bergerak maju
      if (step > currentStep && !validateCurrentStep()) {
        return;
      }

      // Sembunyikan semua content
      contents.forEach((content) => (content.style.display = "none"));

      // Reset semua step ke kondisi default
      steps.forEach((s, index) => {
        if (!s) return; // Skip jika step tidak valid

        // Reset class
        s.classList.remove("active");
        s.classList.remove("completed");

        // Reset nomor step
        const stepNumber = s.querySelector(".ngrstep-number");
        if (stepNumber) {
          stepNumber.style.background =
            options.data.stepNumber?.backgrounddefault || "#e9ecef";
          stepNumber.style.color = "#666";
        }

        // Reset judul step
        const stepTitle = s.querySelector(".step-title");
        if (stepTitle) {
          stepTitle.style.color = "#666";
          stepTitle.style.fontWeight = "normal";
        }

        // Reset garis step
        const stepLine = s.querySelector(".step-line");
        if (stepLine) {
          // stepLine.style.background = '#e9ecef';
          stepLine.style.width = "0%";
        }

        // Tandai step sebelumnya sebagai completed
        if (index < step) {
          s.classList.add("completed");
          if (stepNumber) {
            stepNumber.style.background =
              options.data.stepNumber?.backgroundActiv || "#007bff";
            stepNumber.style.color =
              options.data.stepNumber?.colorText || "#ffffff";
          }
          if (stepLine) {
            // stepLine.style.background = options.data.stepNumber?.backgroundActiv || '#007bff';
            // stepLine.style.width = '100%';
          }
        }
      });

      // Aktifkan step yang dituju
      const activeStep = steps[step];
      if (activeStep) {
        activeStep.classList.add("active");

        // Set style untuk step aktif
        const activeStepNumber = activeStep.querySelector(".ngrstep-number");
        if (activeStepNumber) {
          activeStepNumber.style.background =
            options.data.stepNumber?.backgroundActiv || "#007bff";
          activeStepNumber.style.color =
            options.data.stepNumber?.colorText || "#ffffff";
        }

        const activeStepTitle = activeStep.querySelector(".step-title");
        if (activeStepTitle) {
          activeStepTitle.style.color =
            options.data.stepNumber?.backgroundActiv || "#007bff";
          activeStepTitle.style.fontWeight = "bold";
        }

        const activeStepLine = activeStep.querySelector(".step-line");
        if (activeStepLine) {
          // activeStepLine.style.background = options.data.stepNumber?.backgroundActiv || '#007bff';
          activeStepLine.style.width = "100%";
        }
      }

      // Tampilkan content yang aktif
      if (contents[step]) {
        contents[step].style.display = "block";
      }

      // Update tombol navigasi
      if (prevBtn) prevBtn.style.display = step > 0 ? "inline-block" : "none";
      if (nextBtn)
        nextBtn.style.display =
          step < steps.length - 1 ? "inline-block" : "none";
      if (submitBtn)
        submitBtn.style.display =
          step === steps.length - 1 ? "inline-block" : "none";

      // Update current step
      currentStep = step;
    }

    // Event untuk tombol next
    if (nextBtn) {
      nextBtn.addEventListener("click", () => {
        if (validateCurrentStep()) {
          goToStep(currentStep + 1);
        }
      });
    }

    // Event untuk tombol previous
    if (prevBtn) {
      prevBtn.addEventListener("click", () => {
        goToStep(currentStep - 1);
      });
    }

    // Event untuk klik langsung ke step tertentu
    steps.forEach((step, index) => {
      step.addEventListener("click", () => {
        // Izinkan navigasi ke step sebelumnya tanpa validasi
        if (index <= currentStep) {
          goToStep(index);
        }
        // Untuk step selanjutnya, tetap perlu validasi
        else if (validateCurrentStep()) {
          goToStep(index);
        }
      });
    });

    // Override form submit
    if (form) {
      form.addEventListener("submit", (e) => {
        e.preventDefault();
        if (validateCurrentStep()) {
          if (callbacks.onSubmit) {
            try {
              callbacks.onSubmit(new FormData(form));
            } catch (error) {
              console.error("Error in form submission:", error);
              if (callbacks.onError) {
                callbacks.onError(error);
              }
            }
          }
        }
      });
    }

    // Inisialisasi step pertama
    goToStep(0);

    // Expose fungsi setActiveSteps dan getCurrentStep ke public API
    return {
      setActiveSteps,
      getCurrentStep: () => currentStep,
      goToStep,
      setCurrentStep: (step) => {
        currentStep = step;
      },
    };
  }

  // Tambahkan fungsi setupFileHandlers
  function setupFileHandlers(container) {
    const fileInputs = container.querySelectorAll(".file-input");

    fileInputs.forEach((input) => {
      input.addEventListener("change", function (e) {
        const files = Array.from(e.target.files);
        const maxSize = parseInt(input.dataset.maxSize) * 1024 * 1024; // Convert to bytes
        const previewContainer = input
          .closest(".file-upload-wrapper")
          .querySelector(".file-preview");
        const acceptedTypes = input.accept
          .split(",")
          .map((type) => type.trim().toLowerCase());

        // Clear previous previews
        if (previewContainer) {
          previewContainer.innerHTML = "";
        }

        // Validasi dan preview untuk setiap file
        const validFiles = [];

        files.forEach((file) => {
          // Validasi ekstensi file
          const fileExtension = "." + file.name.split(".").pop().toLowerCase();
          if (!acceptedTypes.includes(fileExtension)) {
            alert(
              `File ${
                file.name
              } tidak diizinkan. Format yang diperbolehkan: ${acceptedTypes.join(
                ", "
              )}`
            );
            return;
          }

          // Validasi ukuran file
          if (file.size > maxSize) {
            alert(
              `File ${file.name} terlalu besar. Maksimal ${
                maxSize / (1024 * 1024)
              }MB`
            );
            return;
          }

          validFiles.push(file);

          // Tambahkan preview
          if (previewContainer) {
            const previewItem = document.createElement("div");
            previewItem.className = "file-preview-item";

            const fileContent = document.createElement("div");
            fileContent.className = "file-content";

            // Cek tipe file untuk preview
            if (file.type.startsWith("image/")) {
              // Preview untuk gambar
              const img = document.createElement("img");
              img.src = URL.createObjectURL(file);
              img.style.maxWidth = "100px";
              img.style.maxHeight = "100px";
              fileContent.appendChild(img);
            } else {
              // Icon untuk file non-gambar
              const { icon, color } = getFileIconAndColor(file.name);
              const iconEl = document.createElement("i");
              iconEl.className = `fas ${icon}`;
              iconEl.style.color = color;
              iconEl.style.fontSize = "48px";
              fileContent.appendChild(iconEl);
            }

            // Info file
            const fileInfo = document.createElement("div");
            fileInfo.className = "file-info";
            fileInfo.innerHTML = `
              <span class="file-name">${file.name}</span>
              <span class="file-size">${formatFileSize(file.size)}</span>
            `;

            // Tombol hapus
            const removeBtn = document.createElement("button");
            removeBtn.type = "button";
            removeBtn.className = "remove-file";
            removeBtn.innerHTML = '<i class="fas fa-times"></i>';
            removeBtn.onclick = () => {
              previewItem.remove();
              // Update input files
              const dt = new DataTransfer();
              const remainingFiles = Array.from(input.files).filter(
                (f) => f !== file
              );
              remainingFiles.forEach((f) => dt.items.add(f));
              input.files = dt.files;
            };

            fileContent.appendChild(fileInfo);
            previewItem.appendChild(fileContent);
            previewItem.appendChild(removeBtn);
            previewContainer.appendChild(previewItem);
          }
        });

        // Update input dengan hanya file yang valid
        const dt = new DataTransfer();
        validFiles.forEach((file) => dt.items.add(file));
        input.files = dt.files;
      });
    });
  }

  // Tambahkan fungsi helper untuk format ukuran file
  function formatFileSize(bytes) {
    if (bytes === 0) return "0 Bytes";
    const k = 1024;
    const sizes = ["Bytes", "KB", "MB", "GB"];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + " " + sizes[i];
  }

  // Tambahkan style untuk preview file

  // Tambahkan fungsi setupDatepicker
  function setupDatepicker(container) {
    const datepickers = container.querySelectorAll(".datepicker");

    datepickers.forEach((input) => {
      // Ambil format tanggal dari konfigurasi
      const fieldName = input.name;
      const fieldConfig = options.data.action[fieldName];
      const dateFormat = fieldConfig ? fieldConfig[3] : "yy-mm-dd"; // Gunakan format dari konfigurasi atau default
      $(input).datepicker({
        dateFormat: dateFormat.replace("dd/mm/yyyy", "dd/mm/yy"), // Sesuaikan format untuk jQuery UI
        changeMonth: true,
        changeYear: true,
        yearRange: "c-100:c+10",
        beforeShow: function (input, inst) {
          setTimeout(function () {
            inst.dpDiv.css({
              top: $(input).offset().top + $(input).outerHeight() + 2,
            });
          }, 0);
        },
        onSelect: function () {
          // Trigger validasi saat tanggal dipilih
          validateInput(this);
        },
        onClose: function () {
          // Trigger validasi saat datepicker ditutup
          validateInput(this);
        },
      });

      // Tambahkan event listener untuk validasi
      input.addEventListener("change", () => validateInput(input));
      input.addEventListener("blur", () => validateInput(input));
    });
  }

  // Fungsi untuk mendapatkan ikon dan warna berdasarkan ekstensi file
  const getFileIconAndColor = (fileName) => {
    const extension = fileName.split(".").pop().toLowerCase();
    switch (extension) {
      // Document Files
      case "pdf":
        return {
          icon: "fa-file-pdf",
          color: "#dc3545", // Merah
        };
      case "doc":
      case "docx":
        return {
          icon: "fa-file-word",
          color: "#0d6efd", // Biru
        };
      case "ppt":
      case "pptx":
        return {
          icon: "fa-file-powerpoint",
          color: "#fd7e14", // Orange
        };
      case "xls":
      case "xlsx":
      case "csv":
        return {
          icon: "fa-file-excel",
          color: "#28a745", // Hijau
        };

      // Image Files
      case "png":
      case "jpg":
      case "jpeg":
      case "gif":
      case "bmp":
      case "svg":
      case "webp":
        return {
          icon: "fa-file-image",
          color: "#198754", // Hijau Tua
        };

      // Code Files
      case "json":
      case "xml":
      case "html":
      case "css":
      case "js":
      case "php":
      case "py":
      case "java":
      case "cpp":
      case "cs":
        return {
          icon: "fa-file-code",
          color: "#6f42c1", // Ungu
        };

      // Archive Files
      case "zip":
      case "rar":
      case "7z":
      case "tar":
      case "gz":
        return {
          icon: "fa-file-archive",
          color: "#795548", // Coklat
        };

      // Text Files
      case "txt":
      case "rtf":
      case "md":
        return {
          icon: "fa-file-alt",
          color: "#17a2b8", // Cyan
        };

      // Audio Files
      case "mp3":
      case "wav":
      case "ogg":
      case "m4a":
      case "flac":
        return {
          icon: "fa-file-audio",
          color: "#e83e8c", // Pink
        };

      // Video Files
      case "mp4":
      case "avi":
      case "mkv":
      case "mov":
      case "wmv":
      case "flv":
      case "webm":
        return {
          icon: "fa-file-video",
          color: "#6610f2", // Ungu Tua
        };

      // Default untuk file tidak dikenal
      default:
        return {
          icon: "fa-file",
          color: "#6c757d", // Abu-abu
        };
    }
  };

  // Tambahkan fungsi validateInput sebelum setupFormValidation
  function validateInput(input) {
    if (!input) return false;

    const formGroup = input.closest(".form-group");
    const errorMessage = formGroup?.querySelector(".error-message");
    const label = formGroup?.querySelector("label")?.textContent || "Field";

    let isValid = true;
    let errorText = "";

    // Reset status validasi
    input.classList.remove("error");
    if (errorMessage) {
      errorMessage.style.display = "none";
    }

    // Tambahkan validasi khusus untuk datepicker
    if (input.classList.contains("datepicker")) {
      const dateValue = input.value.trim();
      if (!dateValue) {
        isValid = false;
        errorText = `${label} harus diisi`;
      } else {
        // Validasi format tanggal (dd/mm/yyyy atau yyyy-mm-dd)
        const dateRegex = /^(\d{4}-\d{2}-\d{2}|\d{2}\/\d{2}\/\d{4})$/;
        if (!dateRegex.test(dateValue)) {
          isValid = false;
          errorText = `Format ${label} tidak valid`;
        } else {
          // Validasi tanggal valid
          const date = new Date(
            dateValue.replace(/(\d{2})\/(\d{2})\/(\d{4})/, "$3-$2-$1")
          );
          if (isNaN(date.getTime())) {
            isValid = false;
            errorText = `${label} tidak valid`;
          }
        }
      }
    }

    // Tambahkan validasi khusus untuk rich text editor
    if (input.closest(".richtext-editor")) {
      const content = input
        .closest(".richtext-editor")
        .querySelector(".richtext-content");
      const minLength = parseInt(content.getAttribute("minlength")) || 0;
      const textContent = content.textContent.trim();

      if (textContent.length < minLength) {
        isValid = false;
        errorText = `${label} minimal ${minLength} karakter`;

        // Tampilkan error message untuk rich text
        const editorError = input
          .closest(".richtext-editor")
          .querySelector(".error-message");
        if (editorError) {
          editorError.textContent = errorText;
          editorError.style.display = "block";
        }
        content.classList.add("error");
      }
      return isValid;
    }

    // Validasi input kosong untuk input biasa
    const inputValue = input.value?.trim() || "";
    const minLength = parseInt(input.getAttribute("minlength")) || 0;

    if (inputValue === "") {
      isValid = false;
      errorText = `${label} harus diisi`;
    } else if (inputValue.length < minLength) {
      isValid = false;
      errorText = `${label} minimal ${minLength} karakter`;
    }

    // Validasi email
    if (input.type === "email" && inputValue) {
      const emailPattern = /^[^@\s]+@[^@\s]+\.[^@\s]+$/;
      if (!emailPattern.test(inputValue)) {
        isValid = false;
        errorText = `Format ${label} tidak valid`;
      }
    }

    // Tampilkan status validasi
    if (!isValid) {
      input.classList.add("error");
      if (errorMessage) {
        errorMessage.textContent = errorText;
        errorMessage.style.display = "block";
      }
    }

    return isValid;
  }

  // Tambahkan fungsi setupFormValidation
  function setupFormValidation(container) {
    const form = container.querySelector("form");
    if (!form) return;

    // Tambahkan setup untuk search input
    setupSearchInputs(container);

    // Tambahkan handler untuk tombol cancel
    const cancelButton = form.querySelector(".btn-cancel");
    if (cancelButton) {
      cancelButton.addEventListener("click", function () {
        // Reset semua input
        form.reset();

        // Bersihkan preview file jika ada
        const filePreviews = form.querySelectorAll(".file-preview");
        filePreviews.forEach((preview) => (preview.innerHTML = ""));

        // Reset rich text editor jika ada
        const richTextContents = form.querySelectorAll(".richtext-content");
        richTextContents.forEach((content) => {
          content.innerHTML = "";
          const hiddenInput = content.parentElement.querySelector(
            'input[type="hidden"]'
          );
          if (hiddenInput) hiddenInput.value = "";
        });

        // Hapus pesan error
        const errorMessages = form.querySelectorAll(".error-message");
        errorMessages.forEach((msg) => (msg.style.display = "none"));

        // Hapus class error dari input
        const inputs = form.querySelectorAll(".error");
        inputs.forEach((input) => input.classList.remove("error"));
      });
    }

    const inputs = form.querySelectorAll("input[data-validation]");
    const toggleButtons = container.querySelectorAll(".toggle-password");

    // Setup password toggle
    toggleButtons.forEach((button) => {
      button.addEventListener("click", function () {
        const input = this.parentElement.querySelector(".password-input");
        const icon = this.querySelector("i");

        if (input.type === "password") {
          input.type = "text";
          icon.classList.remove("fa-eye");
          icon.classList.add("fa-eye-slash");
        } else {
          input.type = "password";
          icon.classList.remove("fa-eye-slash");
          icon.classList.add("fa-eye");
        }
      });
    });

    // Setup validasi input
    inputs.forEach((input) => {
      // Hapus validasi default browser
      input.removeAttribute("required");

      // Tambahkan event listeners
      input.addEventListener("input", () => validateInput(input));
      input.addEventListener("blur", () => validateInput(input));
    });

    // Setup validasi untuk search inputs
    const searchInputs = form.querySelectorAll(".search-input");
    searchInputs.forEach((input) => {
      input.addEventListener("change", () => validateInput(input));
      input.addEventListener("blur", () => validateInput(input));
    });

    // Setup validasi untuk select elements
    const selectInputs = form.querySelectorAll("select");
    selectInputs.forEach((select) => {
      select.addEventListener("change", () => validateInput(select));
      select.addEventListener("blur", () => validateInput(select));
    });

    // Tambahkan setup untuk multibox
    setupMultiboxInputs(container);

    // Setup validasi untuk multibox inputs
    const multiboxInputs = container.querySelectorAll(".multibox-input");
    multiboxInputs.forEach((input) => {
      input.addEventListener("change", () => validateMultibox(input));
      input.addEventListener("blur", () => validateMultibox(input));
    });

    // Tambahkan validasi checkbox groups
    const checkboxGroups = form.querySelectorAll(
      '.checkbox-group[data-validation="true"]'
    );
    checkboxGroups.forEach((group) => {
      const checkboxes = group.querySelectorAll('input[type="checkbox"]');
      checkboxes.forEach((checkbox) => {
        checkbox.addEventListener("change", () => validateCheckboxGroup(group));
      });
    });

    // Tambahkan validasi untuk radio groups
    const radioGroups = form.querySelectorAll('input[type="radio"]');
    radioGroups.forEach((radio) => {
      radio.addEventListener("change", () => validateRadioGroup(radio));
    });

    // Definisikan submitHandler sebagai fungsi biasa
    function submitHandler(e) {
      e.preventDefault();
      e.stopPropagation();

      let isValid = true;

      // Validasi input biasa
      const inputs = form.querySelectorAll("input[data-validation]");
      inputs.forEach((input) => {
        if (!validateInput(input)) {
          isValid = false;
        }
      });

      // Validasi search inputs
      const searchInputs = form.querySelectorAll(".search-input");
      searchInputs.forEach((input) => {
        if (!validateInput(input)) {
          isValid = false;
        }
      });

      // Validasi select inputs
      const selectInputs = form.querySelectorAll("select");
      selectInputs.forEach((select) => {
        if (!validateInput(select)) {
          isValid = false;
        }
      });

      // Validasi multibox inputs
      const multiboxInputs = form.querySelectorAll(".multibox-input");
      multiboxInputs.forEach((input) => {
        if (!validateMultibox(input)) {
          isValid = false;
        }
      });

      // Validasi checkbox groups
      const checkboxGroups = form.querySelectorAll(
        '.checkbox-group[data-validation="true"]'
      );
      checkboxGroups.forEach((group) => {
        if (!validateCheckboxGroup(group)) {
          isValid = false;
        }
      });

      // Validasi radio groups
      const radioGroups = new Set();
      form.querySelectorAll('input[type="radio"]').forEach((radio) => {
        radioGroups.add(radio.name);
      });

      radioGroups.forEach((name) => {
        const firstRadio = form.querySelector(
          `input[type="radio"][name="${name}"]`
        );
        if (!validateRadioGroup(firstRadio)) {
          isValid = false;
        }
      });

      if (isValid) {
        const formData = new FormData(form);
        const data = Object.fromEntries(formData);

        if (callbacks.onSubmit) {
          try {
            callbacks
              .onSubmit(data)
              .then(() => {
                // Reset form
                form.reset();

                // Reset wizard ke step awal jika ada
                const wizardContents = form.querySelectorAll(".wizard-content");
                const wizardSteps = form.querySelectorAll(".wizard-step");
                if (wizardContents.length > 0) {
                  wizardContents.forEach((content, index) => {
                    content.style.display = index === 0 ? "block" : "none";
                  });
                  wizardSteps.forEach((step, index) => {
                    if (index === 0) {
                      step.classList.add("active");
                    } else {
                      step.classList.remove("active");
                    }
                  });
                }

                // Reset tombol wizard
                const prevBtn = form.querySelector(".btn-prev");
                const nextBtn = form.querySelector(".btn-next");
                const submitBtn = form.querySelector(".btn-submit");

                if (prevBtn) prevBtn.style.display = "none";
                if (nextBtn) nextBtn.style.display = "inline-block";
                if (submitBtn) submitBtn.style.display = "none";
              })
              .catch((error) => {
                console.error("Error in form submission:", error);
                if (callbacks.onError) {
                  callbacks.onError(error);
                }
              });
          } catch (error) {
            console.error("Error in form submission:", error);
            if (callbacks.onError) {
              callbacks.onError(error);
            }
          }
        }
      }
    }

    // Simpan submitHandler ke form
    form.submitHandler = submitHandler;

    // Tambahkan event listener
    form.addEventListener("submit", form.submitHandler);

    // Setup validasi untuk rich text editor
    const richTextEditors = container.querySelectorAll(
      '.richtext-editor[data-validation="true"]'
    );
    richTextEditors.forEach((editor) => {
      const content = editor.querySelector(".richtext-content");
      const hiddenInput = editor.querySelector('input[type="hidden"]');

      // Validasi saat mengetik
      content.addEventListener("input", () => {
        hiddenInput.value = content.innerHTML;
        validateInput(hiddenInput);
      });

      // Validasi saat blur
      content.addEventListener("blur", () => {
        validateInput(hiddenInput);
      });
    });
  }

  // Tambahkan fungsi baru untuk setup search inputs
  function setupSearchInputs(container) {
    const searchInputs = container.querySelectorAll(".search-input");

    searchInputs.forEach((input) => {
      const resultsContainer = input.nextElementSibling;
      const searchItems = resultsContainer.querySelectorAll(".search-item");

      // Tambahkan ini untuk menginisialisasi nilai default
      const defaultValue = input.value;
      if (defaultValue) {
        const defaultItem = Array.from(searchItems).find(
          (item) => item.dataset.value === defaultValue
        );
        if (defaultItem) {
          input.value = defaultItem.textContent.trim();
          input.dataset.value = defaultValue;
        }
      }

      // Event saat input difokuskan
      input.addEventListener("focus", () => {
        resultsContainer.style.display = "block";
      });

      // Event saat input kehilangan fokus
      document.addEventListener("click", (e) => {
        if (!input.contains(e.target) && !resultsContainer.contains(e.target)) {
          resultsContainer.style.display = "none";
        }
      });

      // Event saat mengetik
      input.addEventListener("input", (e) => {
        const searchTerm = e.target.value.toLowerCase();

        searchItems.forEach((item) => {
          const text = item.textContent.toLowerCase();
          if (text.includes(searchTerm)) {
            item.style.display = "block";
          } else {
            item.style.display = "none";
          }
        });

        resultsContainer.style.display = "block";
      });

      // Tambahkan validasi saat input kehilangan fokus
      input.addEventListener("blur", () => {
        // Jika input kosong atau nilai tidak valid, reset input
        if (!input.dataset.value) {
          input.value = "";
        }
        validateInput(input);
      });

      // Tambahkan validasi saat memilih item
      searchItems.forEach((item) => {
        item.addEventListener("click", () => {
          input.value = item.textContent.trim();
          input.dataset.value = item.dataset.value;
          resultsContainer.style.display = "none";
          validateInput(input); // Validasi setelah memilih item
        });
      });
    });
  }

  // Tambahkan fungsi baru untuk setup multibox
  function setupMultiboxInputs(container) {
    const multiboxInputs = container.querySelectorAll(".multibox-input");

    multiboxInputs.forEach((input) => {
      const wrapper = input.closest(".input-wrapper");
      const resultsContainer = wrapper.querySelector(".multibox-results");
      const selectedContainer = wrapper.querySelector(".selected-items");
      const checkboxes = wrapper.querySelectorAll(".multibox-checkbox");

      // Event saat input difokuskan
      input.addEventListener("focus", () => {
        resultsContainer.style.display = "block";
      });

      // Sembunyikan hasil saat klik di luar
      document.addEventListener("click", (e) => {
        if (!wrapper.contains(e.target)) {
          resultsContainer.style.display = "none";
        }
      });

      // Event saat mengetik untuk filter
      input.addEventListener("input", (e) => {
        const searchTerm = e.target.value.toLowerCase();
        const items = wrapper.querySelectorAll(".multibox-item");

        items.forEach((item) => {
          const text = item.textContent.toLowerCase();
          item.style.display = text.includes(searchTerm) ? "block" : "none";
        });
      });

      // Event untuk checkbox
      checkboxes.forEach((checkbox) => {
        checkbox.addEventListener("change", () => {
          updateSelectedItems(wrapper);
        });
      });

      // Setup event untuk remove tag
      selectedContainer.addEventListener("click", (e) => {
        if (
          e.target.classList.contains("remove-tag") ||
          e.target.closest(".remove-tag")
        ) {
          const value = e.target.closest(".remove-tag").dataset.value;
          const checkbox = wrapper.querySelector(
            `.multibox-checkbox[value="${value}"]`
          );
          if (checkbox) {
            checkbox.checked = false;
            updateSelectedItems(wrapper);
          }
        }
      });
    });
  }

  // Fungsi helper untuk update selected items
  function updateSelectedItems(wrapper) {
    const selectedContainer = wrapper.querySelector(".selected-items");
    const checkboxes = wrapper.querySelectorAll(".multibox-checkbox:checked");

    selectedContainer.innerHTML = "";

    checkboxes.forEach((checkbox) => {
      const label = checkbox.closest(".multibox-label").textContent.trim();
      const value = checkbox.value;

      const tag = document.createElement("span");
      tag.className = "selected-tag";
      tag.dataset.value = value;
      tag.innerHTML = `
            ${label}
            <button type="button" class="remove-tag" data-value="${value}">
                <i class="fas fa-times"></i>
            </button>
        `;

      selectedContainer.appendChild(tag);
    });
  }

  // Tambahkan fungsi validasi khusus untuk multibox
  function validateMultibox(input) {
    const wrapper = input.closest(".input-wrapper");
    const selectedContainer = wrapper.querySelector(".selected-items");
    const errorMessage = wrapper.parentElement.querySelector(".error-message");
    const label = wrapper.parentElement.querySelector("label").textContent;
    const selectedItems = selectedContainer.querySelectorAll(".selected-tag");
    let isValid = true;
    let errorText = "";

    // Reset error state
    errorMessage.style.display = "none";
    wrapper.classList.remove("error");

    // Validasi minimal harus memilih satu item
    if (selectedItems.length === 0) {
      isValid = false;
      errorText = `${label} harus dipilih minimal satu`;
    }

    // Tampilkan pesan error jika tidak valid
    if (!isValid) {
      errorMessage.textContent = errorText;
      errorMessage.style.display = "block";
      wrapper.classList.add("error");
    }

    return isValid;
  }

  // Tambahkan fungsi validasi checkbox:
  function validateCheckboxGroup(group) {
    const errorMessage = group.parentElement.querySelector(".error-message");
    const label = group.parentElement.querySelector("label").textContent;
    const checkedBoxes = group.querySelectorAll(
      'input[type="checkbox"]:checked'
    );
    let isValid = true;
    let errorText = "";

    // Reset error state
    errorMessage.style.display = "none";
    group.classList.remove("error");

    // Validasi minimal satu checkbox harus dipilih
    if (checkedBoxes.length === 0) {
      isValid = false;
      errorText = `${label} harus dipilih minimal satu`;
    }

    // Tampilkan pesan error jika tidak valid
    if (!isValid) {
      errorMessage.textContent = errorText;
      errorMessage.style.display = "block";
      group.classList.add("error");
    }

    return isValid;
  }

  // Tambahkan fungsi validasi untuk radio group
  function validateRadioGroup(radio) {
    const name = radio.name;
    const radioGroup = radio.closest(".radio-group");
    const errorMessage =
      radioGroup.parentElement.querySelector(".error-message");
    const label = radioGroup.parentElement.querySelector("label").textContent;
    let isValid = true;
    let errorText = "";

    // Reset error state
    errorMessage.style.display = "none";
    radioGroup.classList.remove("error");

    // Cek apakah ada radio yang dipilih
    const checked = document.querySelector(
      `input[type="radio"][name="${name}"]:checked`
    );
    if (!checked) {
      isValid = false;
      errorText = `${label} harus dipilih`;
    }

    // Tampilkan pesan error jika tidak valid
    if (!isValid) {
      errorMessage.textContent = errorText;
      errorMessage.style.display = "block";
      radioGroup.classList.add("error");
    }

    return isValid;
  }

  // Fungsi untuk reset form
  function resetForm(form) {
    // Reset semua input biasa
    form.reset();

    // Reset rich text editors
    const richTextContents = form.querySelectorAll(".richtext-content");
    richTextContents.forEach((content) => {
      content.innerHTML = "";
      const hiddenInput = content.parentElement.querySelector(
        'input[type="hidden"]'
      );
      if (hiddenInput) hiddenInput.value = "";
    });

    // Reset file previews dan multibox selections
    const filePreviews = form.querySelectorAll(".file-preview");
    filePreviews.forEach((preview) => (preview.innerHTML = ""));
    const selectedContainers = form.querySelectorAll(".selected-items");
    selectedContainers.forEach((container) => (container.innerHTML = ""));

    // Reset error messages dan class error
    const errorMessages = form.querySelectorAll(".error-message");
    errorMessages.forEach((msg) => (msg.style.display = "none"));
    const errorInputs = form.querySelectorAll(".error");
    errorInputs.forEach((input) => input.classList.remove("error"));
    // Reset wizard steps
    const wizardSteps = form.querySelectorAll(".wizard-step");
    const wizardContents = form.querySelectorAll(".wizard-content");
    // Reset semua step ke kondisi default
    wizardSteps.forEach((step, index) => {
      // Reset class dan style
      step.classList.remove("active");
      step.classList.remove("completed");

      const stepNumber = step.querySelector(".ngrstep-number");
      if (stepNumber) {
        stepNumber.style.background =
          options.data.stepNumber?.backgrounddefault || "#e9ecef";
        stepNumber.style.color = "#666";
      }

      const stepTitle = step.querySelector(".step-title");
      if (stepTitle) {
        stepTitle.style.color = "#666";
        stepTitle.style.fontWeight = "normal";
      }

      const stepLine = step.querySelector(".step-line");
      if (stepLine) {
        // stepLine.style.background = '#e9ecef';
        stepLine.style.width = "0%";
      }
    });

    // Sembunyikan semua content
    wizardContents.forEach((content) => {
      content.style.display = "none";
    });

    // Aktifkan step pertama
    if (wizardSteps[0]) {
      const firstStep = wizardSteps[0];
      firstStep.classList.add("active");

      const stepNumber = firstStep.querySelector(".ngrstep-number");
      if (stepNumber) {
        stepNumber.style.background =
          options.data.stepNumber?.backgroundActiv || "#007bff";
        stepNumber.style.color =
          options.data.stepNumber?.colorText || "#ffffff";
      }

      const stepTitle = firstStep.querySelector(".step-title");
      if (stepTitle) {
        stepTitle.style.color =
          options.data.stepNumber?.backgroundActiv || "#007bff";
        stepTitle.style.fontWeight = "bold";
      }
    }

    // Tampilkan content step pertama
    if (wizardContents[0]) {
      wizardContents[0].style.display = "block";
    }

    // Reset tombol wizard
    const prevBtn = form.querySelector(".btn-prev");
    const nextBtn = form.querySelector(".btn-next");
    const submitBtn = form.querySelector(".btn-submit");

    if (prevBtn) prevBtn.style.display = "none";
    if (nextBtn) nextBtn.style.display = "inline-block";
    if (submitBtn) submitBtn.style.display = "none";

    // Reset wizard instance
    if (wizardInstance) {
      // Reset current step sebelum memanggil goToStep
      wizardInstance.setCurrentStep(0);

      // Re-initialize wizard untuk memastikan event handlers tetap berfungsi
      const wizardContainer = form.closest(".wizard-container");
      if (wizardContainer) {
        wizardInstance = setupWizard(wizardContainer.parentElement);
      }
    }
  }

  // State untuk callbacks
  const callbacks = {
    onSubmit: null,
    onError: null,
    onReset: null, // Tambahkan callback untuk reset
  };

  // Public API
  return {
    mount: (containerId) => {
      const container = document.getElementById(containerId);
      if (!container) {
        // console.error(`Container dengan ID ${containerId} tidak ditemukan`);
        return;
      }

      // Set container style
      container.style.width = options.width;
      container.style.backgroundColor = options.formBackground;

      const formContent = generateFormFields(options.data.action);
      container.innerHTML = formContent;

      // Setup semua handler
      setupFormValidation(container);
      setupFileHandlers(container);
      setupDatepicker(container);

      // Simpan instance wizard
      wizardInstance = setupWizard(container);

      return {
        setActiveSteps: wizardInstance.setActiveSteps,
        resetForm: () => {
          const form = container.querySelector("form");
          if (form) {
            resetForm(form);
          }
        },
      };
    },
    setCallbacks: (newCallbacks) => Object.assign(callbacks, newCallbacks),
  };
}
// FROM
export function createForm(options = {}) {
  const {
    framework = "ngorei",
    data = {},
    width = "100%",
    formBackground = "#ffffff",
  } = options;
  if ('ngorei'=== options.framework) {
 

  // Fungsi untuk membuat formulir
  function generateFormFields(actions) {
    let formHTML = `
            <div class="container-fluid">
                <form class="fluid">
                    <div class="row row-xs">`;

    const defaultData = options.data.defaultData || {};

    for (const [fieldName, config] of Object.entries(actions)) {
      const [
        type,
        size,
        label,
        placeholder,
        iconPosition,
        iconClass,
        validation,
      ] = config;
      const inputId = type === "datepicker" ? "datepicker" : fieldName;
      const defaultValue = defaultData[fieldName] || "";

      formHTML += `<div class="col-${size}"><div class="form-group">`;
      formHTML += `<label for="${fieldName}">${label}</label>`;

      // Handle berbagai tipe input
      switch (type) {
        case "password":
          formHTML += `
                        <div class="input-wrapper ${iconPosition}-icon">
                            <i class="${iconClass}" style="position: absolute; ${iconPosition}: 10px; top: 50%; transform: translateY(-50%);"></i>
                            <input type="password" 
                                id="${inputId}" 
                                name="${fieldName}" 
                                class="form-control password-input" 
                                placeholder="${placeholder}"
                                required minlength="${validation}"
                                data-validation="true"
                                value="${defaultValue}"
                                autocomplete="current-password"
                                style="padding-${iconPosition}: 35px; padding-right: 40px;">
                            <button type="button" class="toggle-password">
                                <i class="fa-solid fa-eye"></i>
                            </button>
                        </div>`;
          break;
        case "text":
        case "email":
        case "tel":
          const hasIcon =
            iconClass &&
            iconClass !== false &&
            iconPosition &&
            iconPosition !== false;
          let iconStyles = "";
          let inputStyles = "";
          let wrapperClass = "input-wrapper";

          if (hasIcon) {
            wrapperClass += ` has-icon icon-${iconPosition}`;
            if (iconPosition === "right") {
              iconStyles =
                "position: absolute; right: 10px; top: 50%; transform: translateY(-50%);";
              inputStyles = "padding-right: 35px; padding-left: 12px;";
            } else {
              iconStyles =
                "position: absolute; left: 10px; top: 50%; transform: translateY(-50%);";
              inputStyles = "padding-left: 35px; padding-right: 12px;";
            }
          } else {
            inputStyles = "padding: 0.375rem 12px;";
          }

          formHTML += `
            <div class="${wrapperClass}" style="position: relative;">
                ${
                  hasIcon
                    ? `<i class="${iconClass}" style="${iconStyles}"></i>`
                    : ""
                }
                <input type="${type}" 
                    id="${inputId}" 
                    name="${fieldName}"
                    class="form-control" 
                    placeholder="${placeholder}"
                    minlength="${validation}"
                    data-validation="true"
                    value="${defaultValue}"
                    style="${inputStyles} -webkit-appearance: none;"
                    autocomplete="off">
            </div>`;
          break;

        case "richtext":
          formHTML += `
                        <div class="richtext-wrapper">
                            <div id="${inputId}" class="richtext-editor" data-validation="true">
                                <div class="richtext-toolbar">
                                    ${validation.toolbar
                                      .map(
                                        (tool) => `
                                        <button type="button" 
                                            class="toolbar-btn" 
                                            data-command="${tool.command}"
                                            title="${
                                              tool.command
                                                .charAt(0)
                                                .toUpperCase() +
                                              tool.command.slice(1)
                                            }">
                                            <i class="fas ${tool.icon}"></i>
                                        </button>
                                    `
                                      )
                                      .join("")}
                                </div>
                                <div class="richtext-content" 
                                    contenteditable="true" 
                                    data-placeholder="${placeholder}"
                                    style="height: ${validation.height};"
                                    minlength="${validation.minLength || 10}">
                                    ${defaultValue}
                                </div>
                                <div class="error-message" style="display: none; color: red; font-size: 12px; margin-top: 5px;"></div>
                                <input type="hidden" name="${fieldName}" value="${defaultValue}">
                            </div>
                        </div>`;

          // Tambahkan event listener untuk toolbar
          setTimeout(() => {
            const editor = document.getElementById(inputId);
            const toolbar = editor.querySelector(".richtext-toolbar");
            const content = editor.querySelector(".richtext-content");
            const hiddenInput = editor.querySelector('input[type="hidden"]');

            // Update hidden input saat konten berubah
            content.addEventListener("input", () => {
              hiddenInput.value = content.innerHTML;
            });

            // Handle toolbar clicks
            toolbar.addEventListener("click", (e) => {
              const button = e.target.closest(".toolbar-btn");
              if (!button) return;

              e.preventDefault();
              const command = button.dataset.command;

              // Eksekusi perintah formatting
              document.execCommand(command, false, null);

              // Focus kembali ke editor
              content.focus();
            });
          }, 0);
          break;

        case "range":
          const rangeValue = defaultValue || validation.default;
          formHTML += `
                        <div class="range-wrapper">
                            <input type="range" 
                                id="${inputId}" 
                                name="${fieldName}"
                                min="${validation.min}"
                                max="${validation.max}"
                                step="${validation.step}"
                                value="${rangeValue}"
                                class="range-input">
                            ${
                              validation.showValue
                                ? `<output class="range-value">${rangeValue}</output>`
                                : ""
                            }
                        </div>`;
          break;

        case "color":
          formHTML += `
                        <div class="color-picker-wrapper">
                            <input type="color" 
                                id="${inputId}" 
                                name="${fieldName}"
                                value="${
                                  defaultValue || validation.defaultColor
                                }"
                                class="color-input">
                            ${
                              validation.showPalette
                                ? `
                                <div class="color-palette">
                                    <button type="button" data-color="#ff0000"></button>
                                    <button type="button" data-color="#00ff00"></button>
                                    <button type="button" data-color="#0000ff"></button>
                                </div>
                            `
                                : ""
                            }
                        </div>`;
          break;

        case "radio":
          formHTML += `<div class="radio-group">`;
          validation.forEach((option) => {
            const isChecked = defaultValue === option.value ? "checked" : "";
            formHTML += `
                            <div class="form-check">
                                <input type="radio" id="${fieldName}_${option.value}"
                                    name="${fieldName}" value="${option.value}" 
                                    class="form-check-input" ${isChecked}>
                                <label class="form-check-label" for="${fieldName}_${option.value}">
                                    ${option.label}
                                </label>
                            </div>`;
          });
          formHTML += `</div>`;
          break;

        case "select":
          formHTML += `
                        <div class="input-wrapper ${iconPosition}-icon">
                            <i class="${iconClass}" style="position: absolute; ${iconPosition}: 10px; top: 50%; transform: translateY(-50%);"></i>
                            <select id="${inputId}" 
                                name="${fieldName}" 
                                class="form-control"
                                data-validation="true"
                                minlength="1"
                                style="padding-${iconPosition}: 35px;">
                                <option value="">${placeholder}</option>
                                ${validation
                                  .map(
                                    (option) => `
                                    <option value="${option.value}" ${
                                      defaultValue === option.value
                                        ? "selected"
                                        : ""
                                    }>
                                        ${option.label}
                                    </option>`
                                  )
                                  .join("")}
                            </select>
                        </div>`;
          break;

        case "checkbox":
          formHTML += `<div class="checkbox-group" data-validation="true">`;
          validation.forEach((option) => {
            const isChecked =
              Array.isArray(defaultValue) && defaultValue.includes(option.value)
                ? "checked"
                : "";
            formHTML += `
                            <div class="form-check">
                                <input type="checkbox" id="${fieldName}_${option.value}"
                                    name="${fieldName}" value="${option.value}" 
                                    class="form-check-input" ${isChecked}>
                                <label class="form-check-label" for="${fieldName}_${option.value}">
                                    ${option.label}
                                </label>
                            </div>`;
          });
          formHTML += `</div>`;
          break;

        case "search":
          const defaultSearchValue = defaultData[fieldName] || "";
          const defaultSearchItem = validation.find(
            (item) => item.value === defaultSearchValue
          );
          const defaultSearchLabel = defaultSearchItem
            ? defaultSearchItem.label
            : "";

          const hasSearchIcon =
            iconClass &&
            iconClass !== false &&
            iconPosition &&
            iconPosition !== false;
          let searchIconStyles = "";
          let searchInputStyles = "";
          let searchWrapperClass = "input-wrapper";

          if (hasSearchIcon) {
            searchWrapperClass += ` has-icon icon-${iconPosition}`;
            if (iconPosition === "right") {
              searchIconStyles =
                "position: absolute; right: 10px; top: 50%; transform: translateY(-50%);";
              searchInputStyles = "padding-right: 35px; padding-left: 12px;";
            } else {
              searchIconStyles =
                "position: absolute; left: 10px; top: 50%; transform: translateY(-50%);";
              searchInputStyles = "padding-left: 35px; padding-right: 12px;";
            }
          } else {
            searchInputStyles = "padding: 0.375rem 12px;";
          }

          formHTML += `
            <div class="${searchWrapperClass}" style="position: relative;">
                ${
                  hasSearchIcon
                    ? `<i class="${iconClass}" style="${searchIconStyles}"></i>`
                    : ""
                }
                <input type="text" 
                    id="${inputId}" 
                    name="${fieldName}"
                    class="form-control search-input" 
                    placeholder="${placeholder || "Cari..."}"
                    autocomplete="off"
                    value="${defaultSearchLabel}"
                    data-value="${defaultSearchValue}"
                    style="${searchInputStyles} -webkit-appearance: none;">
                <div class="search-results" style="display: none;">
                    <ul class="search-list">
                        ${validation
                          .map(
                            (option) => `
                                <li class="search-item" data-value="${option.value}">
                                    ${option.label}
                                </li>
                            `
                          )
                          .join("")}
                    </ul>
                </div>
            </div>`;
          break;

        case "multibox":
          const selectedValues = Array.isArray(defaultValue)
            ? defaultValue
            : [];
          formHTML += `
                        <div class="input-wrapper ${iconPosition}-icon">
                            <i class="${iconClass}" style="position: absolute; ${iconPosition}: 10px; top: 50%; transform: translateY(-50%);"></i>
                            <input type="text" 
                                id="${inputId}" 
                                name="${fieldName}"
                                class="form-control multibox-input" 
                                placeholder="${
                                  placeholder || "Ketik untuk mencari..."
                                }"
                                autocomplete="off"
                                style="padding-${iconPosition}: 35px;">
                            <div class="selected-items">
                                ${selectedValues
                                  .map((value) => {
                                    const option = validation.find(
                                      (opt) => opt.value === value
                                    );
                                    return option
                                      ? `
                                        <span class="selected-tag" data-value="${value}">
                                            ${option.label}
                                            <button type="button" class="remove-tag" data-value="${value}">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </span>
                                    `
                                      : "";
                                  })
                                  .join("")}
                            </div>
                            <div class="multibox-results" style="display: none;">
                                <ul class="multibox-list">
                                    ${validation
                                      .map(
                                        (option) => `
                                        <li class="multibox-item" data-value="${
                                          option.value
                                        }">
                                            <label class="multibox-label">
                                                <input type="checkbox" class="multibox-checkbox" 
                                                    value="${option.value}"
                                                    ${
                                                      selectedValues.includes(
                                                        option.value
                                                      )
                                                        ? "checked"
                                                        : ""
                                                    }>
                                                ${option.label}
                                            </label>
                                        </li>
                                    `
                                      )
                                      .join("")}
                                </ul>
                            </div>
                        </div>`;
          break;

        case "datepicker":
          const hasDatepickerIcon =
            iconClass &&
            iconClass !== false &&
            iconPosition &&
            iconPosition !== false;
          let datepickerIconStyles = "";
          let datepickerInputStyles = "";
          let datepickerWrapperClass = "input-wrapper";

          if (hasDatepickerIcon) {
            datepickerWrapperClass += ` has-icon icon-${iconPosition}`;
            if (iconPosition === "right") {
              datepickerIconStyles =
                "position: absolute; right: 10px; top: 50%; transform: translateY(-50%);";
              datepickerInputStyles =
                "padding-right: 35px; padding-left: 12px;";
            } else {
              datepickerIconStyles =
                "position: absolute; left: 10px; top: 50%; transform: translateY(-50%);";
              datepickerInputStyles =
                "padding-left: 35px; padding-right: 12px;";
            }
          } else {
            datepickerInputStyles = "padding: 0.375rem 12px;";
          }

          formHTML += `
            <div class="${datepickerWrapperClass}" style="position: relative;">
                ${
                  hasDatepickerIcon
                    ? `<i class="${iconClass}" style="${datepickerIconStyles}"></i>`
                    : ""
                }
                <input type="text" 
                    id="${inputId}" 
                    name="${fieldName}"
                    class="form-control datepicker" 
                    placeholder="${placeholder}"
                    value="${defaultValue}"
                    data-validation="true"
                    minlength="1"
                    autocomplete="off"
                    readonly
                    style="${datepickerInputStyles} -webkit-appearance: none;">
            </div>`;
          break;

        case "file":
          const defaultFileUrl = defaultData[fieldName] || "";
          formHTML += `
                        <div class="input-wrapper ${iconPosition}-icon">
                            <div class="file-upload-wrapper">
                                <div class="file-upload-area">
                                    <input type="file" 
                                        id="${inputId}" 
                                        name="${fieldName}"
                                        class="form-control file-input" 
                                        accept="${validation.accept}"
                                        ${validation.multiple ? "multiple" : ""}
                                        data-max-size="${validation.maxSize}">
                                    <div class="file-upload-content">
                                        <i class="fas fa-cloud-upload-alt"></i>
                                        <p>Browse file to upload</p>
                                        <span class="file-support">Support: ${validation.accept
                                          .replace(/\./g, "")
                                          .toUpperCase()}</span>
                                    </div>
                                </div>
                                ${
                                  validation.preview
                                    ? `
                                    <div class="file-preview">
                                        ${
                                          defaultFileUrl
                                            ? `
                                            <div class="file-preview-item">
                                                <div class="file-content">
                                                    <img src="${defaultFileUrl}" alt="Preview" style="max-width: 100px; max-height: 100px;">
                                                    <div class="file-info">
                                                        <span class="file-name">Current File</span>
                                                    </div>
                                                </div>
                                                <input type="hidden" name="${fieldName}_current" value="${defaultFileUrl}">
                                            </div>
                                        `
                                            : ""
                                        }
                                    </div>
                                `
                                    : ""
                                }
                            </div>
                        </div>`;
          break;

        // Existing datepicker and text cases...
      }

      formHTML += `
                <div class="error-message" style="display: none; color: red; font-size: 12px; margin-top: 5px;">
                    ${
                      type === "tel"
                        ? "Hanya boleh angka dan minimal " +
                          validation +
                          " digit"
                        : "Minimal " + validation + " karakter"
                    }
                </div>
            </div></div>`;
    }

    formHTML += `
                    </div>
                    ${
                      options.data.footer
                        ? `
                        <div class="row row-xs">
                        <div class="col-md-12 form-footer ${
                          options.data.footer.class || ""
                        }" 
                             style="display: flex; justify-content: ${
                               options.data.footer.position === "left"
                                 ? "flex-start"
                                 : options.data.footer.position === "center"
                                 ? "center"
                                 : "flex-end"
                             }; 
                                    gap:5px;">

                            ${
                              options.data.footer.cancel
                                ? `<button type="button" class="btn btn-${options.data.footer.cancel[1]} btn-cancel">${options.data.footer.cancel[0]}</button>`
                                : ""
                            }
                            ${
                              options.data.footer.save
                                ? `<button type="submit" class="btn btn-${options.data.footer.save[1]}">${options.data.footer.save[0]}</button>`
                                : ""
                            }
                        </div>
                        </div>
                    `
                        : ""
                    }
                </form>
            </div>`;
    return formHTML;
  }

  // Fungsi untuk memasang form ke container
  function mountForm(containerId) {
    const container = document.getElementById(containerId);
    if (!container) {
      console.error(`Container dengan ID ${containerId} tidak ditemukan`);
      return;
    }

    const formContent = generateFormFields(options.data.action);
    container.innerHTML = formContent;

    // Setup validasi dan event handlers
    setupFormValidation(container);
    setupFileHandlers(container);
    setupDatepicker(container);
  }

  // Fungsi untuk setup file handlers
  function setupFileHandlers(container) {
    const fileInputs = container.querySelectorAll(".file-input");

    fileInputs.forEach((input) => {
        input.addEventListener("change", function(e) {
            const files = Array.from(e.target.files);
            const maxSize = parseInt(input.dataset.maxSize) * 1024 * 1024;
            const previewContainer = input.closest(".file-upload-wrapper").querySelector(".file-preview");
            const acceptedTypes = input.accept.split(",").map(type => type.trim().toLowerCase());

            if (previewContainer) {
                previewContainer.innerHTML = "";
            }

            // Validasi untuk setiap file
            let hasInvalidFile = false;
            
            files.forEach((file) => {
                // Cek tipe file
                const fileType = file.type.toLowerCase();
                const fileExtension = `.${file.name.split('.').pop().toLowerCase()}`;
                
                // Cek apakah tipe file diizinkan
                const isValidType = acceptedTypes.some(type => {
                    if (type.startsWith(".")) {
                        // Jika accept menggunakan ekstensi (misalnya .pdf)
                        return type === fileExtension;
                    } else {
                        // Jika accept menggunakan MIME type (misalnya application/pdf)
                        return fileType.includes(type);
                    }
                });

                if (!isValidType) {
                    alert(`File ${file.name} tidak diizinkan. Format yang diperbolehkan: ${acceptedTypes.join(", ")}`);
                    hasInvalidFile = true;
                    return;
                }

                // Cek ukuran file
                if (file.size > maxSize) {
                    alert(`File ${file.name} terlalu besar. Maksimal ${maxSize / (1024 * 1024)}MB`);
                    hasInvalidFile = true;
                    return;
                }

                // Jika file valid, tampilkan preview
                if (!hasInvalidFile) {
                    const filePreviewItem = document.createElement("div");
                    filePreviewItem.className = "file-preview-item";

                    const fileContent = document.createElement("div");
                    fileContent.className = "file-content";

                    // Preview untuk file gambar
                    if (file.type.startsWith("image/")) {
                        const reader = new FileReader();
                        reader.onload = function(e) {
                            const imgContainer = document.createElement("div");
                            imgContainer.innerHTML = `
                                <img src="${e.target.result}" alt="Preview" style="max-width: 100px; max-height: 100px;">
                                <div class="file-info">
                                    <span class="file-name">${file.name}</span>
                                    <span class="file-size">${(file.size / 1024).toFixed(2)} KB</span>
                                </div>
                            `;
                            fileContent.appendChild(imgContainer);
                        };
                        reader.readAsDataURL(file);
                    } else {
                        // Preview untuk file non-gambar
                        const fileIcon = getFileIconAndColor(file.name);
                        const iconContainer = document.createElement("div");
                        iconContainer.innerHTML = `
                            <i class="fas ${fileIcon.icon}" style="color: ${fileIcon.color}; font-size: 2em;"></i>
                            <div class="file-info">
                                <span class="file-name">${file.name}</span>
                                <span class="file-size">${(file.size / 1024).toFixed(2)} KB</span>
                            </div>
                        `;
                        fileContent.appendChild(iconContainer);
                    }

                    // Tambahkan tombol hapus
                    const removeBtn = document.createElement("button");
                    removeBtn.type = "button";
                    removeBtn.className = "remove-file";
                    removeBtn.title = "Hapus";
                    removeBtn.innerHTML = '<i class="fas fa-times"></i>';
                    
                    removeBtn.addEventListener("click", function() {
                        filePreviewItem.remove();
                        const dt = new DataTransfer();
                        const remainingFiles = Array.from(input.files)
                            .filter(f => f !== file);
                        remainingFiles.forEach(f => dt.items.add(f));
                        input.files = dt.files;
                    });
                    
                    fileContent.appendChild(removeBtn);
                    filePreviewItem.appendChild(fileContent);
                    previewContainer.appendChild(filePreviewItem);
                }
            });

            // Reset input jika ada file yang tidak valid
            if (hasInvalidFile) {
                input.value = '';
                if (previewContainer) {
                    previewContainer.innerHTML = '';
                }
            }
        });
    });
  }

  // Tambahkan fungsi setupDatepicker
  function setupDatepicker(container) {
    const datepickers = container.querySelectorAll(".datepicker");

    datepickers.forEach((input) => {
      $(input).datepicker({
        dateFormat: "yy-mm-dd",
        changeMonth: true,
        changeYear: true,
        yearRange: "c-100:c+10",
        beforeShow: function (input, inst) {
          // Pastikan datepicker muncul di atas input
          setTimeout(function () {
            inst.dpDiv.css({
              top: $(input).offset().top + $(input).outerHeight() + 2,
            });
          }, 0);
        },
      });
    });
  }

  // Fungsi untuk mendapatkan ikon dan warna berdasarkan ekstensi file
  const getFileIconAndColor = (fileName) => {
    const extension = fileName.split(".").pop().toLowerCase();
    switch (extension) {
      // Document Files
      case "pdf":
        return {
          icon: "fa-file-pdf",
          color: "#dc3545", // Merah
        };
      case "doc":
      case "docx":
        return {
          icon: "fa-file-word",
          color: "#0d6efd", // Biru
        };
      case "ppt":
      case "pptx":
        return {
          icon: "fa-file-powerpoint",
          color: "#fd7e14", // Orange
        };
      case "xls":
      case "xlsx":
      case "csv":
        return {
          icon: "fa-file-excel",
          color: "#28a745", // Hijau
        };

      // Image Files
      case "png":
      case "jpg":
      case "jpeg":
      case "gif":
      case "bmp":
      case "svg":
      case "webp":
        return {
          icon: "fa-file-image",
          color: "#198754", // Hijau Tua
        };

      // Code Files
      case "json":
      case "xml":
      case "html":
      case "css":
      case "js":
      case "php":
      case "py":
      case "java":
      case "cpp":
      case "cs":
        return {
          icon: "fa-file-code",
          color: "#6f42c1", // Ungu
        };

      // Archive Files
      case "zip":
      case "rar":
      case "7z":
      case "tar":
      case "gz":
        return {
          icon: "fa-file-archive",
          color: "#795548", // Coklat
        };

      // Text Files
      case "txt":
      case "rtf":
      case "md":
        return {
          icon: "fa-file-alt",
          color: "#17a2b8", // Cyan
        };

      // Audio Files
      case "mp3":
      case "wav":
      case "ogg":
      case "m4a":
      case "flac":
        return {
          icon: "fa-file-audio",
          color: "#e83e8c", // Pink
        };

      // Video Files
      case "mp4":
      case "avi":
      case "mkv":
      case "mov":
      case "wmv":
      case "flv":
      case "webm":
        return {
          icon: "fa-file-video",
          color: "#6610f2", // Ungu Tua
        };

      // Default untuk file tidak dikenal
      default:
        return {
          icon: "fa-file",
          color: "#6c757d", // Abu-abu
        };
    }
  };

  // Fungsi untuk setup validasi
  function setupFormValidation(container) {
    const form = container.querySelector("form");
    if (!form) return;

    // Tambahkan setup untuk search input
    setupSearchInputs(container);

    // Tambahkan handler untuk tombol cancel
    const cancelButton = form.querySelector(".btn-cancel");
    if (cancelButton) {
      cancelButton.addEventListener("click", function () {
        // Reset semua input
        form.reset();

        // Bersihkan preview file jika ada
        const filePreviews = form.querySelectorAll(".file-preview");
        filePreviews.forEach((preview) => (preview.innerHTML = ""));

        // Reset rich text editor jika ada
        const richTextContents = form.querySelectorAll(".richtext-content");
        richTextContents.forEach((content) => {
          content.innerHTML = "";
          const hiddenInput = content.parentElement.querySelector(
            'input[type="hidden"]'
          );
          if (hiddenInput) hiddenInput.value = "";
        });

        // Hapus pesan error
        const errorMessages = form.querySelectorAll(".error-message");
        errorMessages.forEach((msg) => (msg.style.display = "none"));

        // Hapus class error dari input
        const inputs = form.querySelectorAll(".error");
        inputs.forEach((input) => input.classList.remove("error"));
      });
    }

    const inputs = form.querySelectorAll("input[data-validation]");
    const toggleButtons = container.querySelectorAll(".toggle-password");

    // Setup password toggle
    toggleButtons.forEach((button) => {
      button.addEventListener("click", function () {
        const input = this.parentElement.querySelector(".password-input");
        const icon = this.querySelector("i");

        if (input.type === "password") {
          input.type = "text";
          icon.classList.remove("fa-eye");
          icon.classList.add("fa-eye-slash");
        } else {
          input.type = "password";
          icon.classList.remove("fa-eye-slash");
          icon.classList.add("fa-eye");
        }
      });
    });

    // Setup validasi input
    inputs.forEach((input) => {
      // Hapus validasi default browser
      input.removeAttribute("required");

      // Tambahkan event listeners
      input.addEventListener("input", () => validateInput(input));
      input.addEventListener("blur", () => validateInput(input));
    });

    // Setup validasi untuk search inputs
    const searchInputs = form.querySelectorAll(".search-input");
    searchInputs.forEach((input) => {
      input.addEventListener("change", () => validateInput(input));
      input.addEventListener("blur", () => validateInput(input));
    });

    // Setup validasi untuk select elements
    const selectInputs = form.querySelectorAll("select");
    selectInputs.forEach((select) => {
      select.addEventListener("change", () => validateInput(select));
      select.addEventListener("blur", () => validateInput(select));
    });

    // Tambahkan setup untuk multibox
    setupMultiboxInputs(container);

    // Setup validasi untuk multibox inputs
    const multiboxInputs = container.querySelectorAll(".multibox-input");
    multiboxInputs.forEach((input) => {
      input.addEventListener("change", () => validateMultibox(input));
      input.addEventListener("blur", () => validateMultibox(input));
    });

    // Tambahkan validasi checkbox groups
    const checkboxGroups = form.querySelectorAll(
      '.checkbox-group[data-validation="true"]'
    );
    checkboxGroups.forEach((group) => {
      const checkboxes = group.querySelectorAll('input[type="checkbox"]');
      checkboxes.forEach((checkbox) => {
        checkbox.addEventListener("change", () => validateCheckboxGroup(group));
      });
    });

    // Tambahkan validasi untuk radio groups
    const radioGroups = form.querySelectorAll('input[type="radio"]');
    radioGroups.forEach((radio) => {
      radio.addEventListener("change", () => validateRadioGroup(radio));
    });

    // Prevent default form submission
    form.addEventListener("submit", function (e) {
      e.preventDefault();

      let isValid = true;

      // Validasi input biasa
      const regularInputs = form.querySelectorAll("input[data-validation]");
      regularInputs.forEach((input) => {
        if (!validateInput(input)) {
          isValid = false;
        }
      });

      // Validasi search inputs
      searchInputs.forEach((input) => {
        if (!validateInput(input)) {
          isValid = false;
        }
      });

      // Validasi select inputs
      selectInputs.forEach((select) => {
        if (!validateInput(select)) {
          isValid = false;
        }
      });

      // Validasi multibox inputs
      multiboxInputs.forEach((input) => {
        if (!validateMultibox(input)) {
          isValid = false;
        }
      });

      // Validasi checkbox groups
      checkboxGroups.forEach((group) => {
        if (!validateCheckboxGroup(group)) {
          isValid = false;
        }
      });

      // Validasi radio groups
      const radioGroups = new Set();
      form.querySelectorAll('input[type="radio"]').forEach((radio) => {
        radioGroups.add(radio.name);
      });

      radioGroups.forEach((name) => {
        const firstRadio = form.querySelector(
          `input[type="radio"][name="${name}"]`
        );
        if (!validateRadioGroup(firstRadio)) {
          isValid = false;
        }
      });

      if (isValid) {
        handleSubmit(e);
      }
    });

    // Setup validasi untuk rich text editor
    const richTextEditors = container.querySelectorAll(
      '.richtext-editor[data-validation="true"]'
    );
    richTextEditors.forEach((editor) => {
      const content = editor.querySelector(".richtext-content");
      const hiddenInput = editor.querySelector('input[type="hidden"]');

      content.addEventListener("input", () => {
        hiddenInput.value = content.innerHTML;
        validateInput(hiddenInput);
      });

      content.addEventListener("blur", () => {
        validateInput(hiddenInput);
      });
    });
  }

  // Tambahkan fungsi baru untuk setup search inputs
  function setupSearchInputs(container) {
    const searchInputs = container.querySelectorAll(".search-input");

    searchInputs.forEach((input) => {
      const resultsContainer = input.nextElementSibling;
      const searchItems = resultsContainer.querySelectorAll(".search-item");

      // Tambahkan ini untuk menginisialisasi nilai default
      const defaultValue = input.value;
      if (defaultValue) {
        const defaultItem = Array.from(searchItems).find(
          (item) => item.dataset.value === defaultValue
        );
        if (defaultItem) {
          input.value = defaultItem.textContent.trim();
          input.dataset.value = defaultValue;
        }
      }

      // Event saat input difokuskan
      input.addEventListener("focus", () => {
        resultsContainer.style.display = "block";
      });

      // Event saat input kehilangan fokus
      document.addEventListener("click", (e) => {
        if (!input.contains(e.target) && !resultsContainer.contains(e.target)) {
          resultsContainer.style.display = "none";
        }
      });

      // Event saat mengetik
      input.addEventListener("input", (e) => {
        const searchTerm = e.target.value.toLowerCase();

        searchItems.forEach((item) => {
          const text = item.textContent.toLowerCase();
          if (text.includes(searchTerm)) {
            item.style.display = "block";
          } else {
            item.style.display = "none";
          }
        });

        resultsContainer.style.display = "block";
      });

      // Event saat memilih item
      searchItems.forEach((item) => {
        item.addEventListener("click", () => {
          input.value = item.textContent.trim();
          input.dataset.value = item.dataset.value;
          resultsContainer.style.display = "none";
        });
      });
    });
  }

  // Tambahkan fungsi baru untuk setup multibox
  function setupMultiboxInputs(container) {
    const multiboxInputs = container.querySelectorAll(".multibox-input");

    multiboxInputs.forEach((input) => {
      const wrapper = input.closest(".input-wrapper");
      const resultsContainer = wrapper.querySelector(".multibox-results");
      const selectedContainer = wrapper.querySelector(".selected-items");
      const checkboxes = wrapper.querySelectorAll(".multibox-checkbox");

      // Event saat input difokuskan
      input.addEventListener("focus", () => {
        resultsContainer.style.display = "block";
      });

      // Sembunyikan hasil saat klik di luar
      document.addEventListener("click", (e) => {
        if (!wrapper.contains(e.target)) {
          resultsContainer.style.display = "none";
        }
      });

      // Event saat mengetik untuk filter
      input.addEventListener("input", (e) => {
        const searchTerm = e.target.value.toLowerCase();
        const items = wrapper.querySelectorAll(".multibox-item");

        items.forEach((item) => {
          const text = item.textContent.toLowerCase();
          item.style.display = text.includes(searchTerm) ? "block" : "none";
        });
      });

      // Event untuk checkbox
      checkboxes.forEach((checkbox) => {
        checkbox.addEventListener("change", () => {
          updateSelectedItems(wrapper);
        });
      });

      // Setup event untuk remove tag
      selectedContainer.addEventListener("click", (e) => {
        if (
          e.target.classList.contains("remove-tag") ||
          e.target.closest(".remove-tag")
        ) {
          const value = e.target.closest(".remove-tag").dataset.value;
          const checkbox = wrapper.querySelector(
            `.multibox-checkbox[value="${value}"]`
          );
          if (checkbox) {
            checkbox.checked = false;
            updateSelectedItems(wrapper);
          }
        }
      });
    });
  }

  // Fungsi helper untuk update selected items
  function updateSelectedItems(wrapper) {
    const selectedContainer = wrapper.querySelector(".selected-items");
    const checkboxes = wrapper.querySelectorAll(".multibox-checkbox:checked");

    selectedContainer.innerHTML = "";

    checkboxes.forEach((checkbox) => {
      const label = checkbox.closest(".multibox-label").textContent.trim();
      const value = checkbox.value;

      const tag = document.createElement("span");
      tag.className = "selected-tag";
      tag.dataset.value = value;
      tag.innerHTML = `
            ${label}
            <button type="button" class="remove-tag" data-value="${value}">
                <i class="fas fa-times"></i>
            </button>
        `;

      selectedContainer.appendChild(tag);
    });
  }

  // Tambahkan fungsi validasi khusus untuk multibox
  function validateMultibox(input) {
    const wrapper = input.closest(".input-wrapper");
    const selectedContainer = wrapper.querySelector(".selected-items");
    const errorMessage = wrapper.parentElement.querySelector(".error-message");
    const label = wrapper.parentElement.querySelector("label").textContent;
    const selectedItems = selectedContainer.querySelectorAll(".selected-tag");
    let isValid = true;
    let errorText = "";

    // Reset error state
    errorMessage.style.display = "none";
    wrapper.classList.remove("error");

    // Validasi minimal harus memilih satu item
    if (selectedItems.length === 0) {
      isValid = false;
      errorText = `${label} harus dipilih minimal satu`;
    }

    // Tampilkan pesan error jika tidak valid
    if (!isValid) {
      errorMessage.textContent = errorText;
      errorMessage.style.display = "block";
      wrapper.classList.add("error");
    }

    return isValid;
  }

  // Tambahkan fungsi validasi checkbox:
  function validateCheckboxGroup(group) {
    const errorMessage = group.parentElement.querySelector(".error-message");
    const label = group.parentElement.querySelector("label").textContent;
    const checkedBoxes = group.querySelectorAll(
      'input[type="checkbox"]:checked'
    );
    let isValid = true;
    let errorText = "";

    // Reset error state
    errorMessage.style.display = "none";
    group.classList.remove("error");

    // Validasi minimal satu checkbox harus dipilih
    if (checkedBoxes.length === 0) {
      isValid = false;
      errorText = `${label} harus dipilih minimal satu`;
    }

    // Tampilkan pesan error jika tidak valid
    if (!isValid) {
      errorMessage.textContent = errorText;
      errorMessage.style.display = "block";
      group.classList.add("error");
    }

    return isValid;
  }

  // Tambahkan fungsi validasi untuk radio group
  function validateRadioGroup(radio) {
    const name = radio.name;
    const radioGroup = radio.closest(".radio-group");
    const errorMessage =
      radioGroup.parentElement.querySelector(".error-message");
    const label = radioGroup.parentElement.querySelector("label").textContent;
    let isValid = true;
    let errorText = "";

    // Reset error state
    errorMessage.style.display = "none";
    radioGroup.classList.remove("error");

    // Cek apakah ada radio yang dipilih
    const checked = document.querySelector(
      `input[type="radio"][name="${name}"]:checked`
    );
    if (!checked) {
      isValid = false;
      errorText = `${label} harus dipilih`;
    }

    // Tampilkan pesan error jika tidak valid
    if (!isValid) {
      errorMessage.textContent = errorText;
      errorMessage.style.display = "block";
      radioGroup.classList.add("error");
    }

    return isValid;
  }

  // Handler submit form
  async function handleSubmit(e) {
    e.preventDefault();

    try {
      const form = e.target;
      let isValid = true;

      // Validasi input biasa
      const inputs = form.querySelectorAll("input[data-validation]");
      inputs.forEach((input) => {
        if (!validateInput(input)) {
          isValid = false;
        }
      });

      // Validasi search inputs
      const searchInputs = form.querySelectorAll(".search-input");
      searchInputs.forEach((input) => {
        if (!validateInput(input)) {
          isValid = false;
        }
      });

      // Validasi select inputs
      const selectInputs = form.querySelectorAll("select");
      selectInputs.forEach((select) => {
        if (!validateInput(select)) {
          isValid = false;
        }
      });

      // Validasi multibox inputs
      const multiboxInputs = form.querySelectorAll(".multibox-input");
      multiboxInputs.forEach((input) => {
        if (!validateMultibox(input)) {
          isValid = false;
        }
      });

      // Validasi checkbox groups
      const checkboxGroups = form.querySelectorAll(
        '.checkbox-group[data-validation="true"]'
      );
      checkboxGroups.forEach((group) => {
        if (!validateCheckboxGroup(group)) {
          isValid = false;
        }
      });

      // Validasi radio groups
      const radioGroups = new Set();
      form.querySelectorAll('input[type="radio"]').forEach((radio) => {
        radioGroups.add(radio.name);
      });

      radioGroups.forEach((name) => {
        const firstRadio = form.querySelector(
          `input[type="radio"][name="${name}"]`
        );
        if (!validateRadioGroup(firstRadio)) {
          isValid = false;
        }
      });

      if (isValid) {
        const formData = new FormData(form);
        const data = Object.fromEntries(formData);

        if (callbacks.onSubmit) {
          await callbacks.onSubmit(data);
          form.reset();
        }
      }
    } catch (error) {
      if (callbacks.onError) {
        callbacks.onError(error);
      }
      console.error("Error submitting form:", error);
    }
  }

  // Fungsi untuk validasi input
  function validateInput(input) {
    let errorMessage;
    let label;

    // Cek apakah input adalah rich text editor
    if (input.closest(".richtext-editor")) {
      const editor = input.closest(".richtext-editor");
      errorMessage = editor.querySelector(".error-message");
      label = editor
        .closest(".richtext-wrapper")
        .parentElement.querySelector("label").textContent;
    } else {
      errorMessage = input
        .closest(".form-group")
        .querySelector(".error-message");
      label = input.closest(".form-group").querySelector("label").textContent;
    }

    const minLength = parseInt(input.getAttribute("minlength")) || 0;
    let isValid = true;
    let errorText = "";

    // Reset error state
    if (errorMessage) {
      errorMessage.style.display = "none";
      errorMessage.textContent = "";
    }
    input.classList.remove("error");

    // Validasi input kosong
    if (!input.value.trim()) {
      isValid = false;
      errorText = `${label} harus diisi`;
    }

    // Validasi khusus untuk rich text editor
    if (input.closest(".richtext-editor")) {
      const editor = input.closest(".richtext-editor");
      const content = editor.querySelector(".richtext-content");
      const minLength = parseInt(content.getAttribute("minlength")) || 0;
      const textContent = content.textContent.trim();

      if (textContent.length < minLength) {
        isValid = false;
        errorText = `${label} minimal ${minLength} karakter`;
      }
    }

    // Tampilkan pesan error jika tidak valid
    if (!isValid && errorMessage) {
      errorMessage.textContent = errorText;
      errorMessage.style.display = "block";
      if (!input.closest(".richtext-editor")) {
        input.classList.add("error");
      } else {
        input.closest(".richtext-editor").classList.add("error");
      }
    }

    return isValid;
  }

  // State untuk callbacks
  const callbacks = {
    onSubmit: null,
    onError: null,
  };

  // Public API
  return {
    mount: mountForm,
    setCallbacks: (newCallbacks) => Object.assign(callbacks, newCallbacks),
  };
    
  } else {
    return false; 
  }
}

// PRECODE
export function getLanguageIcon(language) {
  if (!language || typeof language !== "string") {
    return "fas fa-code";
  }
  const iconMap = {
    html: "fab fa-html5",
    css: "fab fa-css3-alt",
    js: "fab fa-js",
    javascript: "fab fa-js",
    python: "fab fa-python",
    php: "fab fa-php",
    java: "fab fa-java",
    react: "fab fa-react",
    vue: "fab fa-vuejs",
    angular: "fab fa-angular",
    node: "fab fa-node-js",
    sass: "fab fa-sass",
    wordpress: "fab fa-wordpress",
    git: "fab fa-git-alt",
    json: "fas fa-brackets-curly",
    wrapped: "fas fa-brackets-curly",
  };

  return iconMap[language.toLowerCase()] || "fas fa-code"; // Default icon
}
// PRE CODE
export function wrapCodeWithTerminal() {
  const codeBlocks = document.querySelectorAll("pre > code:not(.wrapped)");
  codeBlocks.forEach((codeBlock) => {
    // Tandai kode yang sudah diproses
    codeBlock.classList.add("wrapped");

    const classAttr = codeBlock.className;
    const language = classAttr.replace("language-", "").replace(" wrapped", ""); // Hapus class wrapped dari string language
    const title = codeBlock.getAttribute("title") || "";
    const defaultHeight = 200; // Nilai default dalam pixel
    const setmaxHeight =
      parseInt(codeBlock.getAttribute("maxHeight")) || defaultHeight;
    const pxmaxHeight = setmaxHeight + "px"; // Gunakan variabel yang sudah dikonversi
    const languageIcon = getLanguageIcon(language);
    let newsTitiel = "";
    if (title) {
      newsTitiel = title + "." + language;
    } else {
      newsTitiel = language;
    }
    const terminal = document.createElement("div");
    terminal.className = "terminal";

    const terminalHeader = document.createElement("div");
    terminalHeader.className = "terminal-header";
    terminalHeader.innerHTML = `
      <span>
        <i class="${languageIcon}" aria-hidden="true"></i> 
        ${newsTitiel} 
      </span>
      <div class="terminal-buttons">
        <button onclick="copyCode(this)" class="terminal-copy-btn" aria-label="Salin kode">
          <i class="icon-feather-copy" aria-hidden="true"></i> Copy
        </button>
      </div>
    `;

    const terminalFooter = document.createElement("div");
    terminalFooter.className = "terminal-footer";
    if (codeBlock.offsetHeight > setmaxHeight) {
      terminalFooter.innerHTML = `
        <button class="terminal-code-btn" onclick="toggleCode(this)">Lihat selengkapnya</button>
      `;
    }

    const preElement = codeBlock.parentElement;

    preElement.parentNode.insertBefore(terminal, preElement);
    terminal.appendChild(terminalHeader);
    terminal.appendChild(preElement);
    terminal.appendChild(terminalFooter);

    if (codeBlock.offsetHeight > setmaxHeight) {
      preElement.style.maxHeight = pxmaxHeight;
      preElement.style.overflow = "hidden";
    }
  });
}
window.toggleCode = function (button) {
  const terminal = button.closest(".terminal");
  const preElement = terminal.querySelector("pre");
  const codeElement = preElement.querySelector("code");
  const defaultHeight = 200;
  const maxHeight =
    (parseInt(codeElement.getAttribute("maxHeight")) || defaultHeight) + "px";

  if (preElement.style.maxHeight === maxHeight) {
    preElement.style.maxHeight = "none";
    button.textContent = "Lihat lebih sedikit";
  } else {
    preElement.style.maxHeight = maxHeight;
    button.textContent = "Lihat selengkapnya";
  }
};
// MODAL FROM
export function createFromModal(options = {}) {
  const modalState = {
    isOpen: false,
    history: [],
    stack: [], // untuk nested modals
    activeModals: 0,
    previousFocus: null, // untuk accessibility
    modalInstances: new Map(), // tracking multiple modals
    zIndexCounter: 1050, // manajemen z-index untuk nested modals
  };

  // Tambahkan breakpoints untuk responsivitas
  const responsiveOptions = {
    width: {
      sm: "95%",
      md: "70%",
      lg: "50%",
    },
    maxWidth: {
      sm: "100%",
      md: "600px",
      lg: "800px",
    },
  };

  // Tambahkan opsi animasi
  const animations = {
    slide: `@keyframes slideIn {from { transform: translateY(-100px); opacity: 0; }to { transform: translateY(0); opacity: 1; }}@keyframes slideOut {from { transform: translateY(0); opacity: 1; }to { transform: translateY(-100px); opacity: 0; }}`,
    fade: `@keyframes fadeIn {from { opacity: 0; }to { opacity: 1; }}@keyframes fadeOut {from { opacity: 1; }to { opacity: 0; }}`,
    zoom: `@keyframes zoomIn {from { transform: scale(0.5); opacity: 0; }to { transform: scale(1); opacity: 1; }}@keyframes zoomOut {from { transform: scale(1); opacity: 1; }to { transform: scale(0.5); opacity: 0; }}`,
  };
  const {
    framework = "ngorei",
    title,
    content,
    data = {},
    width = "70%",
    height = "400px",
    maxWidth = "500px",
    backdropColor = "rgba(0,0,0,0.5)",
    modalBackground = "#ffffff",
    animation = "slide", // slide, fade, zoom
    position = "center", // top, center, bottom
    closeOnEsc = true,
    showFooter = false,
    footerContent = "",
    draggable = false,
    resizable = false,
    minWidth = 200,
    minHeight = 100,
    animationDuration = "0.3s", // tambahkan durasi animasi
  } = options;

  // Tambahkan fungsi untuk membuat formulir otomatis
  function generateFormFields(actions) {
    let formHTML = '<form class="ngr-form">';
    formHTML += '<div class="row row-xs">';

    const defaultData = options.data.defaultData || {};

    for (const [fieldName, config] of Object.entries(actions)) {
      const [
        type,
        size,
        label,
        placeholder,
        iconPosition,
        iconClass,
        validation,
      ] = config;
      const inputId = type === "datepicker" ? "datepicker" : fieldName;
      const defaultValue = defaultData[fieldName] || "";

      formHTML += `<div class="col-${size}"><div class="form-group">`;
      formHTML += `<label for="${fieldName}">${label}</label>`;

      // Handle berbagai tipe input
      switch (type) {
        case "password":
          formHTML += `
                        <div class="input-wrapper ${iconPosition}-icon">
                            <i class="${iconClass}" style="position: absolute; ${iconPosition}: 10px; top: 50%; transform: translateY(-50%);"></i>
                            <input type="password" id="${inputId}" name="${fieldName}" 
                                class="form-control password-input" 
                                placeholder="${placeholder}"
                                required minlength="${validation}"
                                data-validation="true"
                                value="${defaultValue}"
                                style="padding-${iconPosition}: 35px; padding-right: 40px;">
                            <button type="button" class="toggle-password">
                                <i class="fa-solid fa-eye"></i>
                            </button>
                        </div>`;
          break;
        case "text":
        case "email":
        case "tel":
          formHTML += `
                        <div class="input-wrapper ${iconPosition}-icon">
                            <i class="${iconClass}" style="position: absolute; ${iconPosition}: 10px; top: 50%; transform: translateY(-50%);"></i>
                            <input type="${type}" id="${inputId}" name="${fieldName}"
                                class="form-control" 
                                placeholder="${placeholder}"
                                required minlength="${validation}"
                                data-validation="true"
                                value="${defaultValue}"
                                style="padding-${iconPosition}: 35px;">
                        </div>`;
          break;

        case "richtext":
          formHTML += `
                        <div class="richtext-wrapper">
                            <div id="${inputId}" class="richtext-editor">
                                <div class="richtext-toolbar">
                                    ${validation.toolbar
                                      .map(
                                        (tool) =>
                                          `<button type="button" 
                                            class="toolbar-btn" 
                                            data-command="${tool}"
                                            title="${
                                              tool.charAt(0).toUpperCase() +
                                              tool.slice(1)
                                            }">
                                            <i class="fas fa-${tool}"></i>
                                        </button>
                                    `
                                      )
                                      .join("")}
                                </div>
                                <div class="richtext-content" 
                                    contenteditable="true" 
                                    data-placeholder="${placeholder}"
                                    style="height: ${validation.height};">
                                    ${defaultValue}
                                </div>
                                <input type="hidden" name="${fieldName}" value="${defaultValue}">
                            </div>
                        </div>`;

          // Tambahkan event listener untuk toolbar
          setTimeout(() => {
            const editor = document.getElementById(inputId);
            const toolbar = editor.querySelector(".richtext-toolbar");
            const content = editor.querySelector(".richtext-content");
            const hiddenInput = editor.querySelector('input[type="hidden"]');

            // Update hidden input saat konten berubah
            content.addEventListener("input", () => {
              hiddenInput.value = content.innerHTML;
            });

            // Handle toolbar clicks
            toolbar.addEventListener("click", (e) => {
              const button = e.target.closest(".toolbar-btn");
              if (!button) return;

              e.preventDefault();
              const command = button.dataset.command;

              // Eksekusi perintah formatting
              document.execCommand(command, false, null);

              // Focus kembali ke editor
              content.focus();
            });
          }, 0);
          break;

        case "range":
          const rangeValue = defaultValue || validation.default;
          formHTML += `
                        <div class="range-wrapper">
                            <input type="range" 
                                id="${inputId}" 
                                name="${fieldName}"
                                min="${validation.min}"
                                max="${validation.max}"
                                step="${validation.step}"
                                value="${rangeValue}"
                                class="range-input">
                            ${
                              validation.showValue
                                ? `<output class="range-value">${rangeValue}</output>`
                                : ""
                            }
                        </div>`;
          break;

        case "color":
          formHTML += `
                        <div class="color-picker-wrapper">
                            <input type="color" 
                                id="${inputId}" 
                                name="${fieldName}"
                                value="${
                                  defaultValue || validation.defaultColor
                                }"
                                class="color-input">
                            ${
                              validation.showPalette
                                ? `
                                <div class="color-palette">
                                    <button type="button" data-color="#ff0000"></button>
                                    <button type="button" data-color="#00ff00"></button>
                                    <button type="button" data-color="#0000ff"></button>
                                </div>
                            `
                                : ""
                            }
                        </div>`;
          break;

        case "radio":
          formHTML += `<div class="radio-group">`;
          validation.forEach((option) => {
            const isChecked = defaultValue === option.value ? "checked" : "";
            formHTML += `
                            <div class="form-check">
                                <input type="radio" id="${fieldName}_${option.value}"
                                    name="${fieldName}" value="${option.value}" 
                                    class="form-check-input" ${isChecked}>
                                <label class="form-check-label" for="${fieldName}_${option.value}">
                                    ${option.label}
                                </label>
                            </div>`;
          });
          formHTML += `</div>`;
          break;

        case "select":
          formHTML += `
                        <div class="input-wrapper ${iconPosition}-icon">
                            <i class="${iconClass}" style="position: absolute; ${iconPosition}: 10px; top: 50%; transform: translateY(-50%);"></i>
                            <select id="${inputId}" name="${fieldName}" class="form-control" required
                                style="padding-${iconPosition}: 35px;">
                                <option value="">${placeholder}</option>
                                ${validation
                                  .map(
                                    (option) =>
                                      `<option value="${option.value}" ${
                                        defaultValue === option.value
                                          ? "selected"
                                          : ""
                                      }>
                                        ${option.label}
                                    </option>`
                                  )
                                  .join("")}
                            </select>
                        </div>`;
          break;

        case "checkbox":
          formHTML += `<div class="checkbox-group">`;
          validation.forEach((option) => {
            const isChecked =
              Array.isArray(defaultValue) && defaultValue.includes(option.value)
                ? "checked"
                : "";
            formHTML += `
                            <div class="form-check">
                                <input type="checkbox" id="${fieldName}_${option.value}"
                                    name="${fieldName}" value="${option.value}" 
                                    class="form-check-input" ${isChecked}>
                                <label class="form-check-label" for="${fieldName}_${option.value}">
                                    ${option.label}
                                </label>
                            </div>`;
          });
          formHTML += `</div>`;
          break;

        case "search":
          formHTML += `
                        <div class="input-wrapper ${iconPosition}-icon">
                            <i class="${iconClass}" style="position: absolute; ${iconPosition}: 10px; top: 50%; transform: translateY(-50%);"></i>
                            <input type="text" 
                                id="${inputId}" 
                                name="${fieldName}"
                                class="form-control search-input" 
                                placeholder="${placeholder || "Cari..."}"
                                autocomplete="off"
                                style="padding-${iconPosition}: 35px;">
                            <div class="search-results" style="display: none;">
                                <ul class="search-list">
                                    ${validation
                                      .map(
                                        (option) => `
                                        <li class="search-item" data-value="${option.value}">
                                            ${option.label}
                                        </li>
                                    `
                                      )
                                      .join("")}
                                </ul>
                            </div>
                        </div>`;
          break;

        case "multibox":
          const selectedValues = Array.isArray(defaultValue)
            ? defaultValue
            : [];
          formHTML += `
                        <div class="input-wrapper ${iconPosition}-icon">
                            <i class="${iconClass}" style="position: absolute; ${iconPosition}: 10px; top: 50%; transform: translateY(-50%);"></i>
                            <input type="text" 
                                id="${inputId}" 
                                name="${fieldName}"
                                class="form-control multibox-input" 
                                placeholder="${
                                  placeholder || "Ketik untuk mencari..."
                                }"
                                autocomplete="off"
                                style="padding-${iconPosition}: 35px;">
                            <div class="selected-items">
                                ${selectedValues
                                  .map((value) => {
                                    const option = validation.find(
                                      (opt) => opt.value === value
                                    );
                                    return option
                                      ? `
                                        <span class="selected-tag" data-value="${value}">
                                            ${option.label}
                                            <button type="button" class="remove-tag" data-value="${value}">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </span>
                                    `
                                      : "";
                                  })
                                  .join("")}
                            </div>
                            <div class="multibox-results" style="display: none;">
                                <ul class="multibox-list">
                                    ${validation
                                      .map(
                                        (option) => `
                                        <li class="multibox-item" data-value="${
                                          option.value
                                        }">
                                            <label class="multibox-label">
                                                <input type="checkbox" class="multibox-checkbox" 
                                                    value="${option.value}"
                                                    ${
                                                      selectedValues.includes(
                                                        option.value
                                                      )
                                                        ? "checked"
                                                        : ""
                                                    }>
                                                ${option.label}
                                            </label>
                                        </li>
                                    `
                                      )
                                      .join("")}
                                </ul>
                            </div>
                        </div>`;
          break;

        case "datepicker":
          formHTML += `
                        <div class="input-wrapper ${iconPosition}-icon">
                            <i class="${iconClass}" style="position: absolute; ${iconPosition}: 10px; top: 50%; transform: translateY(-50%);"></i>
                            <input type="text" 
                                id="${inputId}" 
                                name="${fieldName}"
                                class="form-control datepicker" 
                                placeholder="${placeholder}"
                                value="${defaultValue}"
                                autocomplete="off"
                                readonly
                                style="padding-${iconPosition}: 35px;">
                        </div>`;
          break;

        case "file":
          const defaultFileUrl = defaultData[fieldName] || "";
          formHTML += `
                        <div class="input-wrapper ${iconPosition}-icon">
                            <div class="file-upload-wrapper">
                                <div class="file-upload-area">
                                    <input type="file" 
                                        id="${inputId}" 
                                        name="${fieldName}"
                                        class="form-control file-input" 
                                        accept="${validation.accept}"
                                        ${validation.multiple ? "multiple" : ""}
                                        data-max-size="${validation.maxSize}">
                                    <div class="file-upload-content">
                                        <i class="fas fa-cloud-upload-alt"></i>
                                        <p>Browse file to upload</p>
                                        <span class="file-support">Support: ${validation.accept
                                          .replace(/\./g, "")
                                          .toUpperCase()}</span>
                                    </div>
                                </div>
                                ${
                                  validation.preview
                                    ? `
                                    <div class="file-preview">
                                        ${
                                          defaultFileUrl
                                            ? `
                                            <div class="file-preview-item">
                                                <div class="file-content">
                                                    <img src="${defaultFileUrl}" alt="Preview" style="max-width: 100px; max-height: 100px;">
                                                    <div class="file-info">
                                                        <span class="file-name">Current File</span>
                                                    </div>
                                                </div>
                                                <input type="hidden" name="${fieldName}_current" value="${defaultFileUrl}">
                                            </div>
                                        `
                                            : ""
                                        }
                                    </div>
                                `
                                    : ""
                                }
                            </div>
                        </div>`;
          break;

        // Existing datepicker and text cases...
      }

      formHTML += `
                <div class="error-message" style="display: none; color: red; font-size: 12px; margin-top: 5px;">
                    ${
                      type === "tel"
                        ? "Hanya boleh angka dan minimal " +
                          validation +
                          " digit"
                        : "Minimal " + validation + " karakter"
                    }
                </div>
            </div></div>`;
    }

    formHTML += "</div></form>";
    return formHTML;
  }

  // Modifikasi fungsi loadContent
  async function loadContent(content) {
    try {
      // Jika content adalah 'Form', langsung generate form
      if (content === "Form") {
        const formFields = generateFormFields(options.data.action);
        return formFields;
      }

      return content;
    } catch (error) {
      console.error("Error in loadContent:", error);
      return `<div class="error">Error: ${error.message}</div>`;
    }
  }

  // Modifikasi template untuk menambahkan loading state
  const loadingHTML = '<div class="loading">Memuat konten...</div>';

  // Cek apakah content adalah URL atau Form
  const isURL =
    content &&
    (content.startsWith("http://") || content.startsWith("https://"));
  const isForm = content === "Form";
  const initialContent = isURL ? loadingHTML : isForm ? "" : content;

  const modalTemplates = {
    ngorei: `
            <div class="ngr-modal" style="background-color: ${backdropColor}; z-index: 1050;">
                <div id="drmodal" class="ngr-modal-content" style="width: ${width}; background-color: ${modalBackground}; z-index: 1051;">
                    <div id="draggable" class="ngr-modal-header">
                        <h2>${title}</h2>
                        <span class="ngr-close">&times;</span>
                    </div>
                    <div class="ngr-modalbody" style="max-height: ${height}; overflow-y: auto;">
                        <div class="modal-body">${initialContent}</div>
                    </div>
                     <div class="contentFooter"></div>
                </div>
            </div>
        `,
    bootstrap: `
            <div class="modal fade" tabindex="-1" style="z-index: 1050;">
                <div class="modal-backdrop" style="background-color: ${backdropColor}; z-index: 1040; "></div>
                <div id="drmodal" class="modal-dialog" style="width: ${width}; max-width: ${maxWidth}; z-index: 1051;">
                    <div class="modal-content" style=" background-color: ${modalBackground};">
                        <div id="draggable" class="modal-header">
                            <h5 class="modal-title">${title}</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                    <div class="ngr-modalbody"style="max-height: ${height}; overflow-y: auto;">
                        <div class="modal-body">${content}</div>
                    </div>
                         <div class="contentFooter"></div>
                    </div>
                </div>
            </div>
        `,
    tailwind: `
            <div class="fixed inset-0 z-50">
                <div class="fixed inset-0 transition-opacity bg-opacity-75" style="background-color: ${backdropColor}"></div>
                <div id="drmodal" class="fixed inset-0 z-10 overflow-y-auto">
                    <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
                        <div class="relative transform overflow-hidden rounded-lg bg-white text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg">
                            <div class="bg-white px-4 pb-4 pt-5 sm:p-6 sm:pb-4">
                                <div class="flex justify-between items-center">
                                    <h3 id="draggable" class="text-lg font-semibold">${title}</h3>
                                    <button class="modal-close-btn text-gray-400 hover:text-gray-500 p-2">&times;</button>
                                </div>
                                <div class="ngr-modalbody" style="max-height: ${height}; overflow-y: auto;">
                                    <div class="mt-2 modal-body">${initialContent}</div>
                                </div>
                                 <div class="contentFooter"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `,
    uikit: `
            <div class="uk-modal">
                <div id="drmodal" style="width:${width};"class="uk-modal-dialog">
                    <button class="uk-modal-close-default" type="button" uk-close></button>
                    <div id="draggable" class="uk-modal-header">
                        <h2 class="uk-modal-title" id="draggable">${title}</h2>
                    </div>
                    <div  class="uk-modal-body"style="max-height: ${height}; overflow-y: auto;"">
                        <div class="modal-body">
                         ${initialContent} 
                        </div>
                    </div>
                         <div class="contentFooter"></div>
                </div>
            </div>
        `,
    bulma: `
            <div class="modal">
                <div class="modal-background"></div>
                <div id="drmodal" class="modal-card">
                    <header id="draggable" class="modal-card-head">
                        <p class="modal-card-title">${title}</p>
                        <button class="delete" aria-label="close"></button>
                    </header>
                    <section class="modal-card-body ">
                     <div  class="ngr-modalbody"style="max-height: ${height}; overflow-y: auto;"">
                        <div class="modal-body">${initialContent}</div>
                     </div>
                     <div class="contentFooter"></div>
                    </section>
                </div>
            </div>
        `,
  };

  // Gabungkan semua CSS menjadi satu
  const combinedStyle = document.createElement("style");
  combinedStyle.textContent = `
        /* Modal Base Styles */
        .ngr-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: ${backdropColor};
            z-index: 1050;
        }

        .ngr-modal-content {
            background-color: ${modalBackground};
            padding: 1px;
            width: ${width};
            max-width: ${maxWidth};
            height: ${height};
            border-radius: 5px;
            position: fixed;
            z-index: 1051;
            display: flex;
            flex-direction: column;
            ${
              !draggable
                ? `
                left: 50%;
                ${
                  position === "top"
                    ? "top: 20px; transform: translateX(-50%);"
                    : position === "bottom"
                    ? "bottom: 20px; transform: translateX(-50%);"
                    : "top: 50%; transform: translate(-50%, -50%);"
                }
            `
                : `
                left: calc(50% - ${parseInt(width) / 2}px);
                top: ${
                  position === "top"
                    ? "20px"
                    : position === "bottom"
                    ? "auto"
                    : "25%"
                };
            `
            }
            animation: ${animation}In ${animationDuration} ease-out;
        }

        /* Modal Header Styles */
        .ngr-modal-header {
            flex-shrink: 0;
            display: flex;
            padding: 10px;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 5px;
            ${draggable ? "cursor: move;" : ""}
        }
        ${animations[animation]}
        ${animations.fade}
        
    `;

  // Hapus style lama jika ada
  const oldStyles = document.querySelectorAll("style");
  oldStyles.forEach((style) => {
    if (
      style.textContent.includes("ngr-modal") ||
      style.textContent.includes("form-group") ||
      style.textContent.includes("input-wrapper")
    ) {
      style.remove();
    }
  });

  // Tambahkan style baru
  document.head.appendChild(combinedStyle);

  // Buat elemen modal
  const modal = document.createElement("div");
  modal.innerHTML = modalTemplates[framework];
  const modalElement = modal.firstElementChild;

  // Tambahkan ke body
  document.body.appendChild(modalElement);

  // Ganti implementasi draggable yang lama dengan yang baru
  if (draggable) {
    $(modalElement.querySelector("#drmodal")).draggable({
      handle: "#draggable",
      scroll: false,
      start: function () {
        $(this).css({
          transform: "none",
        });
      },
    });

    // Tambahkan style minimal yang diperlukan
    const dragStyle = document.createElement("style");
    dragStyle.textContent = `
            .ngr-modal-header {
                cursor: move;
            }
        `;
    document.head.appendChild(dragStyle);
  }

  // Fungsi untuk memancarkan event
  function dispatchModalEvent(eventName) {
    const event = new CustomEvent(eventName, {
      detail: { modal: modalElement },
    });
    modalElement.dispatchEvent(event);
  }

  // Tambahkan state untuk callbacks
  const callbacks = {
    onSubmit: null,
    onCancel: null,
    onError: null,
  };

  // Tambahkan method untuk set callbacks
  function sendCallbacks(newCallbacks) {
    Object.assign(callbacks, newCallbacks);
  }

  // Tambahkan fungsi validateInput
  function validateInput(input) {
    const errorMessage =
      input.parentElement.parentElement.querySelector(".error-message");
    const minLength = parseInt(input.getAttribute("minlength"));
    const isValid = input.value.length >= minLength;

    if (!isValid) {
      input.classList.add("invalid");
      errorMessage.style.display = "block";
    } else {
      input.classList.remove("invalid");
      errorMessage.style.display = "none";
    }

    return isValid;
  }

  // Modifikasi setupFormValidation untuk menggunakan validateInput
  function setupFormValidation(modalElement) {
    const form = modalElement.querySelector("form");
    if (!form) return;

    const inputs = form.querySelectorAll("input[data-validation]");

    // Tambahkan validasi real-time
    inputs.forEach((input) => {
      input.addEventListener("input", () => validateInput(input));
      input.addEventListener("blur", () => validateInput(input));
    });

    async function handleSubmit(e) {
      e.preventDefault();

      try {
        let isValid = true;
        const formData = new FormData(form);
        const data = Object.fromEntries(formData);

        // Validasi semua input
        inputs.forEach((input) => {
          if (!validateInput(input)) {
            isValid = false;
          }
        });

        if (isValid) {
          // Tambahkan data dari rich text editor jika ada
          const richTextEditors = form.querySelectorAll(".richtext-content");
          richTextEditors.forEach((editor) => {
            const hiddenInput = editor.nextElementSibling;
            if (hiddenInput) {
              data[hiddenInput.name] = editor.innerHTML;
            }
          });

          // Panggil callback onSubmit jika ada
          if (callbacks.onSubmit) {
            await callbacks.onSubmit(data);
          }

          // Reset form setelah submit berhasil
          form.reset();
          // closeModal();
        }
      } catch (error) {
        if (callbacks.onError) {
          callbacks.onError(error);
        }
        console.error("Error submitting form:", error);
      }
    }

    form.addEventListener("submit", handleSubmit);

    // Tambahkan handler untuk tombol cancel
    const cancelBtn = modalElement.querySelector("#cancelBtn");
    if (cancelBtn) {
      cancelBtn.addEventListener("click", () => {
        if (callbacks.onCancel) {
          callbacks.onCancel();
        }
        closeModal();
      });
    }
  }

  // Tambahkan CSS untuk styling

  // Modifikasi fungsi showModal untuk menambahkan footer
  async function showModal() {
    dispatchModalEvent("modalbeforeopen");

    if (isURL || isForm) {
      const modalBody = modalElement.querySelector(".modal-body");
      const contentFooter = modalElement.querySelector(".contentFooter");

      if (modalBody) {
        try {
          if (isForm) {
            const contentData = await loadContent("Form");
            modalBody.innerHTML = contentData;

            // Inisialisasi datepicker dengan format yang sesuai
            $(".datepicker").each(function () {
              const format = $(this).attr("placeholder") || "yy/mm/dd";
              $(this).datepicker({
                dateFormat: format,
                changeMonth: true,
                changeYear: true,
                showAnim: "fadeIn",
                yearRange: "c-100:c+10",
                beforeShow: function (input, inst) {
                  // Pastikan datepicker selalu di atas modal
                  inst.dpDiv.css({
                    zIndex: 1060,
                  });
                },
              });
            });

            // Tambahkan footer ke contentFooter
            if (options.data.footer) {
              contentFooter.innerHTML = `
                                <div class="modal-footer ${
                                  options.data.footer.class || ""
                                }">

                                    ${
                                      options.data.footer.cancel
                                        ? `<button type="button" class="btn btn-${options.data.footer.cancel[1]}" id="cancelBtn">${options.data.footer.cancel[0]}</button>`
                                        : ""
                                    }
                                    ${
                                      options.data.footer.save
                                        ? `<button type="submit" class="btn btn-${options.data.footer.save[1]}">${options.data.footer.save[0]}</button>`
                                        : ""
                                    }
                                </div>
                            `;

              // Tambahkan event listener untuk tombol cancel
              const cancelBtn = contentFooter.querySelector("#cancelBtn");
              if (cancelBtn) {
                cancelBtn.addEventListener("click", () => {
                  closeModal(); // Menggunakan fungsi closeModal yang sudah ada
                });
              }

              // Tambahkan event listener untuk tombol submit
              const submitBtn = contentFooter.querySelector(
                'button[type="submit"]'
              );
              if (submitBtn) {
                submitBtn.addEventListener("click", (e) => {
                  e.preventDefault();
                  const form = modalBody.querySelector("form");
                  if (form) {
                    form.dispatchEvent(new Event("submit"));
                  }
                });
              }
            }

            setupFormValidation(modalElement);
          } else {
            modalBody.innerHTML = loadingHTML;
            const contentData = await loadContent(content);
            modalBody.innerHTML = contentData;
          }
        } catch (error) {
          console.error("Error loading content:", error);
          modalBody.innerHTML = '<div class="error">Gagal memuat konten</div>';
        }
      }
    }

    switch (framework) {
      case "ngorei":
        modalElement.style.display = "block";
        break;
      case "bootstrap":
        modalElement.classList.add("show");
        modalElement.style.display = "block";
        document.body.classList.add("modal-open");
        break;
      case "tailwind":
        // Hapus hidden class dan tampilkan modal
        modalElement.classList.remove("hidden");
        modalElement.style.display = "block";
        break;
      case "uikit":
        UIkit.modal(modalElement).show();
        break;
      case "bulma":
        modalElement.classList.add("is-active");
        break;
    }

    dispatchModalEvent("modalopen");
  }

  // Ubah selector untuk tombol close
  const closeButtons = {
    ngorei: ".ngr-close",
    bootstrap: ".btn-close",
    tailwind: ".modal-close-btn",
    uikit: ".uk-modal-close-default",
    bulma: ".delete",
  };

  // Modifikasi fungsi closeModal
  function closeModal() {
    dispatchModalEvent("modalbeforeclose");

    if (framework === "tailwind") {
      // Langsung sembunyikan modal untuk Tailwind
      modalElement.style.display = "none";
      dispatchModalEvent("modalclose");
      return;
    }

    const modalContent =
      modalElement.querySelector(".relative.transform") ||
      modalElement.querySelector(".ngr-modal-content");
    if (modalContent) {
      modalContent.style.animation = `${animation}Out ${animationDuration} ease-in`;
    }

    setTimeout(() => {
      switch (framework) {
        case "ngorei":
          modalElement.style.display = "none";
          break;
        case "bootstrap":
          modalElement.classList.remove("show");
          modalElement.style.display = "none";
          document.body.classList.remove("modal-open");
          break;
        case "tailwind":
          modalElement.classList.add("hidden");
          // Jangan set display none di sini
          break;
        case "uikit":
          UIkit.modal(modalElement).hide();
          break;
        case "bulma":
          modalElement.classList.remove("is-active");
          break;
      }

      if (modalContent) {
        modalContent.style.animation = "";
      }

      dispatchModalEvent("modalclose");
    }, parseFloat(animationDuration) * 1000);
  }

  // Tambahkan event listener untuk backdrop click khusus Tailwind
  if (framework === "tailwind") {
    const backdrop = modalElement.querySelector(
      ".fixed.inset-0.transition-opacity"
    );
    if (backdrop) {
      backdrop.addEventListener("click", (e) => {
        if (e.target === backdrop) {
          closeModal();
        }
      });
    }
  }

  // Pastikan event listener untuk tombol close terpasang
  const closeButton = modalElement.querySelector(closeButtons[framework]);
  if (closeButton) {
    closeButton.addEventListener("click", closeModal);
  } else {
    console.error(`Close button tidak ditemukan untuk framework ${framework}`);
  }

  // Tambahkan event listener untuk tombol toggle-password
  document.addEventListener("click", function (event) {
    if (event.target.closest(".toggle-password")) {
      const input = event.target
        .closest(".input-wrapper")
        .querySelector("input");
      const icon = event.target.querySelector("i");
      if (input.type === "password") {
        input.type = "text";
        icon.classList.replace("fa-eye", "fa-eye-slash");
      } else {
        input.type = "password";
        icon.classList.replace("fa-eye-slash", "fa-eye");
      }
    }
  });

  // Tambahkan event listener untuk search functionality
  document.addEventListener("input", function (event) {
    if (event.target.classList.contains("search-input")) {
      const wrapper = event.target.closest(".input-wrapper");
      const resultsDiv = wrapper.querySelector(".search-results");
      const searchItems = wrapper.querySelectorAll(".search-item");
      const searchTerm = event.target.value.toLowerCase();

      if (searchTerm.length > 0) {
        resultsDiv.style.display = "block";
        searchItems.forEach((item) => {
          const text = item.textContent.toLowerCase();
          if (text.includes(searchTerm)) {
            item.classList.remove("hidden");
          } else {
            item.classList.add("hidden");
          }
        });
      } else {
        resultsDiv.style.display = "none";
      }
    }
  });

  // Event listener untuk memilih item
  document.addEventListener("click", function (event) {
    if (event.target.classList.contains("search-item")) {
      const wrapper = event.target.closest(".input-wrapper");
      const input = wrapper.querySelector("input");
      const selectedValue = event.target.dataset.value;

      input.value = selectedValue;
      const resultsDiv = wrapper.querySelector(".search-results");
      resultsDiv.style.display = "none";
    }
  });

  // Tambahkan event listeners untuk multibox functionality
  document.addEventListener("input", function (event) {
    if (event.target.classList.contains("multibox-input")) {
      const wrapper = event.target.closest(".input-wrapper");
      const resultsDiv = wrapper.querySelector(".multibox-results");
      const items = wrapper.querySelectorAll(".multibox-item");
      const searchTerm = event.target.value.toLowerCase();

      if (searchTerm.length > 0) {
        resultsDiv.style.display = "block";
        items.forEach((item) => {
          const text = item.textContent.toLowerCase();
          if (text.includes(searchTerm)) {
            item.classList.remove("hidden");
          } else {
            item.classList.add("hidden");
          }
        });
      } else {
        resultsDiv.style.display = "none";
      }
    }
  });

  // Event listener untuk checkbox changes
  document.addEventListener("change", function (event) {
    if (event.target.classList.contains("multibox-checkbox")) {
      const wrapper = event.target.closest(".input-wrapper");
      const selectedItems = wrapper.querySelector(".selected-items");
      const input = wrapper.querySelector(".multibox-input");
      const label = event.target.closest(".multibox-label").textContent.trim();
      const value = event.target.value;

      if (event.target.checked) {
        // Tambah tag
        const tag = document.createElement("span");
        tag.className = "selected-tag";
        tag.dataset.value = value;
        tag.innerHTML = `
                    ${label}
                    <button type="button" class="remove-tag" data-value="${value}">
                        <i class="fas fa-times"></i>
                    </button>
                `;
        selectedItems.appendChild(tag);
      } else {
        // Hapus tag
        const existingTag = selectedItems.querySelector(
          `[data-value="${value}"]`
        );
        if (existingTag) existingTag.remove();
      }

      // Clear input
      input.value = "";
      input.focus();
    }
  });

  // Event listener untuk remove tags
  document.addEventListener("click", function (event) {
    if (event.target.closest(".remove-tag")) {
      const tag = event.target.closest(".selected-tag");
      const wrapper = tag.closest(".input-wrapper");
      const value = tag.dataset.value;
      const checkbox = wrapper.querySelector(`input[value="${value}"]`);

      if (checkbox) checkbox.checked = false;
      tag.remove();
    }
  });

  // Close results when clicking outside
  document.addEventListener("click", function (event) {
    if (!event.target.closest(".input-wrapper")) {
      const allResults = document.querySelectorAll(".multibox-results");
      allResults.forEach((results) => {
        results.style.display = "none";
      });
    }
  });

  // Tambahkan event listener untuk input file
  document.addEventListener("change", function (event) {
    if (event.target.classList.contains("file-input")) {
      const fileInput = event.target;
      const maxSize = parseInt(fileInput.getAttribute("data-max-size")); // dalam MB
      const previewContainer = fileInput
        .closest(".input-wrapper")
        .querySelector(".file-preview");
      const files = Array.from(fileInput.files);
      let totalSize = 0;
      let invalidFiles = [];

      // Validasi setiap file
      files.forEach((file) => {
        const fileSize = file.size / (1024 * 1024); // Convert ke MB
        totalSize += fileSize;

        if (fileSize > maxSize) {
          invalidFiles.push({
            name: file.name,
            size: fileSize.toFixed(2),
          });
        }
      });

      // Cek jika ada file yang melebihi batas
      if (invalidFiles.length > 0) {
        let errorMessage = "File berikut melebihi batas " + maxSize + " MB:\n";
        invalidFiles.forEach((file) => {
          errorMessage += `- ${file.name} (${file.size} MB)\n`;
        });

        // Reset input file
        fileInput.value = "";
        previewContainer.innerHTML = "";

        // Tampilkan pesan error (dengan fallback ke alert biasa)
        if (typeof Swal !== "undefined") {
          Swal.fire({
            icon: "error",
            title: "Ukuran File Terlalu Besar",
            text: errorMessage,
            confirmButtonText: "OK",
          });
        } else {
          alert(errorMessage);
        }
        return;
      }

      // Jika semua file valid, tampilkan preview
      previewContainer.innerHTML = "";

      // Tambahkan pesan jika tidak ada file yang dipilih
      if (files.length === 0) {
        previewContainer.innerHTML =
          '<div class="no-file-selected">Tidak ada file yang dipilih</div>';
        return;
      }

      files.forEach((file, index) => {
        const fileReader = new FileReader();
        fileReader.onload = function (e) {
          const fileURL = e.target.result;
          const fileElement = document.createElement("div");
          fileElement.className = "file-preview-item";

          // Format ukuran file
          let fileSize = (file.size / (1024 * 1024)).toFixed(2); // dalam MB
          if (fileSize < 1) {
            fileSize = (file.size / 1024).toFixed(2) + " KB";
          } else {
            fileSize = fileSize + " MB";
          }

          // Tentukan ikon berdasarkan tipe file
          let fileIcon = "fa-file-alt";
          if (file.type.startsWith("image/")) {
            fileIcon = "fa-file-image";
          } else if (file.type.includes("pdf")) {
            fileIcon = "fa-file-pdf";
          } else if (
            file.type.includes("word") ||
            file.name.endsWith(".doc") ||
            file.name.endsWith(".docx")
          ) {
            fileIcon = "fa-file-word";
          } else if (file.type.includes("json")) {
            fileIcon = "fa-file-code";
          }

          fileElement.innerHTML = `
                        <div class="file-content">
                            ${
                              file.type.startsWith("image/")
                                ? `<img src="${fileURL}" alt="${file.name}" style="max-width: 100px; max-height: 100px;">`
                                : `<i class="fas ${fileIcon}"></i>`
                            }
                            <div class="file-info">
                                <span class="file-name">${file.name}</span>
                                <span class="file-size">${fileSize}</span>
                            </div>
                        </div>
                        <button type="button" class="remove-file" data-index="${index}">
                            <i class="fas fa-times"></i>
                        </button>
                    `;

          previewContainer.appendChild(fileElement);
        };
        fileReader.readAsDataURL(file);
      });
    }
  });

  // Tambahkan CSS untuk styling file info

  // Tambahkan event listeners untuk drag & drop
  // document.addEventListener('DOMContentLoaded', function() {
  const fileAreas = document.querySelectorAll(".file-upload-area");

  fileAreas.forEach((area) => {
    ["dragenter", "dragover", "dragleave", "drop"].forEach((eventName) => {
      area.addEventListener(eventName, preventDefaults, false);
    });

    function preventDefaults(e) {
      e.preventDefault();
      e.stopPropagation();
    }

    ["dragenter", "dragover"].forEach((eventName) => {
      area.addEventListener(eventName, () => {
        area.classList.add("dragover");
      });
    });

    ["dragleave", "drop"].forEach((eventName) => {
      area.addEventListener(eventName, () => {
        area.classList.remove("dragover");
      });
    });

    area.addEventListener("drop", handleDrop);
  });

  function handleDrop(e) {
    const dt = e.dataTransfer;
    const files = dt.files;
    const input = this.querySelector('input[type="file"]');

    input.files = files;
    input.dispatchEvent(new Event("change"));
  }
  // });

  document.addEventListener("click", function (event) {
    if (event.target.closest(".remove-file")) {
      const button = event.target.closest(".remove-file");
      const filePreviewItem = button.closest(".file-preview-item");
      const fileInput = filePreviewItem
        .closest(".input-wrapper")
        .querySelector(".file-input");
      const index = parseInt(button.dataset.index);

      // Buat FileList baru tanpa file yang dihapus
      const dt = new DataTransfer();
      Array.from(fileInput.files).forEach((file, i) => {
        if (i !== index) dt.items.add(file);
      });

      // Update file input dengan FileList baru
      fileInput.files = dt.files;

      // Hapus preview item
      filePreviewItem.remove();

      // Jika tidak ada file tersisa, kosongkan input
      if (fileInput.files.length === 0) {
        fileInput.value = "";
      }
    }
  });

  return {
    show: showModal,
    close: closeModal,
    element: modalElement,
    sendCallbacks,
    onOpen: (callback) => {
      modalElement.addEventListener("modalopen", callback);
    },
    onClose: (callback) => {
      modalElement.addEventListener("modalclose", callback);
    },
    beforeOpen: (callback) => {
      modalElement.addEventListener("modalbeforeopen", callback);
    },
    beforeClose: (callback) => {
      modalElement.addEventListener("modalbeforeclose", callback);
    },
  };
}

//Modal
export function createModal(options = {}) {
  // Tambahan state management
  const modalState = {
    isOpen: false,
    history: [],
    stack: [], // untuk nested modals
    activeModals: 0,
    previousFocus: null, // untuk accessibility
    modalInstances: new Map(), // tracking multiple modals
    zIndexCounter: 1050, // manajemen z-index untuk nested modals
  };

  // Tambahkan breakpoints untuk responsivitas
  const responsiveOptions = {
    width: {
      sm: "95%",
      md: "70%",
      lg: "50%",
    },
    maxWidth: {
      sm: "100%",
      md: "600px",
      lg: "800px",
    },
  };

  // Tambahkan opsi animasi
  const animations = {
    slide: `
        @keyframes slideIn {
            from { transform: translateY(-100px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        @keyframes slideOut {
            from { transform: translateY(0); opacity: 1; }
            to { transform: translateY(-100px); opacity: 0; }
        }
    `,
    fade: `
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        @keyframes fadeOut {
            from { opacity: 1; }
            to { opacity: 0; }
        }
    `,
    zoom: `
        @keyframes zoomIn {
            from { transform: scale(0.5); opacity: 0; }
            to { transform: scale(1); opacity: 1; }
        }
        @keyframes zoomOut {
            from { transform: scale(1); opacity: 1; }
            to { transform: scale(0.5); opacity: 0; }
        }
    `,
  };
  const {
    framework = "ngorei",
    title,
    content,
    data = {},
    width = "70%",
    height = "400px",
    maxWidth = "500px",
    backdropColor = "rgba(0,0,0,0.5)",
    modalBackground = "#ffffff",
    animation = "slide", // slide, fade, zoom
    position = "center", // top, center, bottom
    closeOnEsc = true,
    showFooter = false,
    footerContent = "",
    draggable = false,
    resizable = false,
    minWidth = 200,
    minHeight = 100,
    animationDuration = "0.3s", // tambahkan durasi animasi
  } = options;

  // Fungsi untuk memuat konten dari URL dengan metode POST
  async function loadContent(url) {
    try {
      const response = await fetch(app.url + "/worker/" + md5Str(url), {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({
          ...data,
          key: md5Str(url) || "",
          brief: url || "",
          timestamp: new Date().getTime(),
        }),
      });

      if (!response.ok) {
        throw new Error("Gagal memuat konten: " + response.statusText);
      }
      const responseData = await response.text();
      return responseData;
    } catch (error) {
      return `<div class="error">Error: ${error.message}</div>`;
    }
  }

  // Modifikasi template untuk menambahkan loading state
  const loadingHTML = '<div class="loading">Memuat konten...</div>';

  // Cek apakah content adalah URL
  const isURL =content &&(content.startsWith("http://") || content.startsWith("https://"));
  const initialContent = isURL ? loadingHTML : content;

  const modalTemplates = {
    ngorei: `
            <div class="ngr-modal" style="background-color: ${backdropColor}; z-index: 1050;">
                <div id="drmodal" class="ngr-modal-content" style="width: ${width}; background-color: ${modalBackground}; z-index: 1051;">
                    <div id="draggable" class="ngr-modal-header">
                        <h2>${title}</h2>
                        <span class="ngr-close">&times;</span>
                    </div>
                    <div class="ngr-modalbody" style="max-height: ${height}; overflow-y: auto;">
                        <div class="modal-body">${initialContent}</div>
                    </div>
                </div>
            </div>
        `,
    bootstrap: `
            <div class="modal fade" tabindex="-1" style="z-index: 1050;">
                <div class="modal-backdrop" style="background-color: ${backdropColor}; z-index: 1040; "></div>
                <div id="drmodal" class="modal-dialog" style="width: ${width}; max-width: ${maxWidth}; z-index: 1051;">
                    <div class="modal-content" style=" background-color: ${modalBackground};">
                        <div id="draggable" class="modal-header">
                            <h5 class="modal-title">${title}</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                    <div class="ngr-modalbody"style="max-height: ${height}; overflow-y: auto;">
                        <div class="modal-body">${content}</div>
                    </div>
                        
                    </div>
                </div>
            </div>
        `,
    tailwind: `
            <div class="fixed inset-0 z-50">
                <div class="fixed inset-0 transition-opacity bg-opacity-75" style="background-color: ${backdropColor}"></div>
                <div id="drmodal" class="fixed inset-0 z-10 overflow-y-auto">
                    <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
                        <div class="relative transform overflow-hidden rounded-lg bg-white text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg">
                            <div class="bg-white px-4 pb-4 pt-5 sm:p-6 sm:pb-4">
                                <div class="flex justify-between items-center">
                                    <h3 id="draggable" class="text-lg font-semibold">${title}</h3>
                                    <button class="modal-close-btn text-gray-400 hover:text-gray-500 p-2">&times;</button>
                                </div>
                                <div class="ngr-modalbody" style="max-height: ${height}; overflow-y: auto;">
                                    <div class="mt-2 modal-body">${initialContent}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `,
    uikit: `
            <div class="uk-modal">
                <div id="drmodal" style="width:${width};"class="uk-modal-dialog">
                    <button class="uk-modal-close-default" type="button" uk-close></button>
                    <div id="draggable" class="uk-modal-header">
                        <h2 class="uk-modal-title" id="draggable">${title}</h2>
                    </div>
                    <div  class="uk-modal-body"style="max-height: ${height}; overflow-y: auto;"">
                        <div class="modal-body">
                         ${initialContent} 
                        </div>
                    </div>
                </div>
            </div>
        `,
    bulma: `
            <div class="modal">
                <div class="modal-background"></div>
                <div id="drmodal" class="modal-card">
                    <header id="draggable" class="modal-card-head">
                        <p class="modal-card-title">${title}</p>
                        <button class="delete" aria-label="close"></button>
                    </header>
                    <section class="modal-card-body ngr-modalbody" style="max-height: ${height}; overflow-y: auto;">
                        <div class="modal-body">${initialContent}</div>
                    </section>
                </div>
            </div>
        `,
  };

  // Ubah CSS default untuk modal ngorei
  if (framework === "ngorei") {
    const style = document.createElement("style");
    style.textContent = `
            .ngr-modal {
                display: none;
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background-color: ${backdropColor};
                z-index: 1050;
            }
            .modal-body{
                padding:10px;      
            }
            .ngr-modal-content {
                background-color: ${modalBackground};
                padding:1px;
                width: ${width};
                max-width: ${maxWidth};
                height: ${height};
                border-radius: 5px;
                position: fixed;
                z-index: 1051;
                display: flex;
                flex-direction: column;
                ${
                  !draggable
                    ? `
                    left: 50%;
                    ${
                      position === "top"
                        ? "top: 20px; transform: translateX(-50%);"
                        : position === "bottom"
                        ? "bottom: 20px; transform: translateX(-50%);"
                        : "top: 50%; transform: translate(-50%, -50%);"
                    }
                `
                    : `
                    left: calc(50% - ${parseInt(width) / 2}px);
                    top: ${
                      position === "top"
                        ? "20px"
                        : position === "bottom"
                        ? "auto"
                        : "25%"
                    };
                `
                }
                animation: ${animation}In ${animationDuration} ease-out;
            }
      
            ${animations[animation]}
            .ngr-modal {
                animation: fadeIn ${animationDuration} ease-out;
            }
            ${animations.fade}
        `;
    document.head.appendChild(style);
  }

  // Buat elemen modal
  const modal = document.createElement("div");
  modal.innerHTML = modalTemplates[framework];
  const modalElement = modal.firstElementChild;

  // Tambahkan ke body
  document.body.appendChild(modalElement);

  // Ganti implementasi draggable yang lama dengan yang baru
  if (draggable) {
    $(modalElement.querySelector("#drmodal")).draggable({
      handle: "#draggable",
      scroll: false,
      start: function () {
        $(this).css({
          transform: "none",
        });
      },
    });

    // Tambahkan style minimal yang diperlukan
    const dragStyle = document.createElement("style");
    dragStyle.textContent = `
            .ngr-modal-header {
                cursor: move;
            }
        `;
    document.head.appendChild(dragStyle);
  }

  // Fungsi untuk memancarkan event
  function dispatchModalEvent(eventName) {
    const event = new CustomEvent(eventName, {
      detail: { modal: modalElement },
    });
    modalElement.dispatchEvent(event);
  }

  // Modifikasi fungsi showModal
  async function showModal() {
    dispatchModalEvent("modalbeforeopen");

    if (isURL) {
      const modalBody = modalElement.querySelector(
        ".modal-body, .ngr-modal-body .modal-body"
      );
      if (modalBody) {
        try {
          modalBody.innerHTML = '<div class="loading">Memuat konten...</div>';
          const contentData = await loadContent(content);
          modalBody.innerHTML = contentData;
        } catch (error) {
          console.error("Error loading content:", error);
          modalBody.innerHTML = '<div class="error">Gagal memuat konten</div>';
        }
      }
    }

    switch (framework) {
      case "ngorei":
        modalElement.style.display = "block";
        break;
      case "bootstrap":
        modalElement.classList.add("show");
        modalElement.style.display = "block";
        document.body.classList.add("modal-open");
        break;
      case "tailwind":
        // Hapus hidden class dan tampilkan modal
        modalElement.classList.remove("hidden");
        modalElement.style.display = "block";
        break;
      case "uikit":
        UIkit.modal(modalElement).show();
        break;
      case "bulma":
        modalElement.classList.add("is-active");
        break;
    }

    dispatchModalEvent("modalopen");
  }

  // Ubah selector untuk tombol close
  const closeButtons = {
    ngorei: ".ngr-close",
    bootstrap: ".btn-close",
    tailwind: ".modal-close-btn",
    uikit: ".uk-modal-close-default",
    bulma: ".delete",
  };

  // Modifikasi fungsi closeModal
  function closeModal() {
    dispatchModalEvent("modalbeforeclose");

    if (framework === "tailwind") {
      // Langsung sembunyikan modal untuk Tailwind
      modalElement.style.display = "none";
      dispatchModalEvent("modalclose");
      return;
    }

    const modalContent =
      modalElement.querySelector(".relative.transform") ||
      modalElement.querySelector(".ngr-modal-content");
    if (modalContent) {
      modalContent.style.animation = `${animation}Out ${animationDuration} ease-in`;
    }

    setTimeout(() => {
      switch (framework) {
        case "ngorei":
          modalElement.style.display = "none";
          break;
        case "bootstrap":
          modalElement.classList.remove("show");
          modalElement.style.display = "none";
          document.body.classList.remove("modal-open");
          break;
        case "tailwind":
          modalElement.classList.add("hidden");
          // Jangan set display none di sini
          break;
        case "uikit":
          UIkit.modal(modalElement).hide();
          break;
        case "bulma":
          modalElement.classList.remove("is-active");
          break;
      }

      if (modalContent) {
        modalContent.style.animation = "";
      }

      dispatchModalEvent("modalclose");
    }, parseFloat(animationDuration) * 1000);
  }

  // Tambahkan event listener untuk backdrop click khusus Tailwind
  if (framework === "tailwind") {
    const backdrop = modalElement.querySelector(
      ".fixed.inset-0.transition-opacity"
    );
    if (backdrop) {
      backdrop.addEventListener("click", (e) => {
        if (e.target === backdrop) {
          closeModal();
        }
      });
    }
  }

  // Pastikan event listener untuk tombol close terpasang
  const closeButton = modalElement.querySelector(closeButtons[framework]);
  if (closeButton) {
    closeButton.addEventListener("click", closeModal);
  } else {
    console.error(`Close button tidak ditemukan untuk framework ${framework}`);
  }

  return {
    show: showModal,
    close: closeModal,
    element: modalElement,
    onOpen: (callback) => {
      modalElement.addEventListener("modalopen", callback);
    },
    onClose: (callback) => {
      modalElement.addEventListener("modalclose", callback);
    },
    beforeOpen: (callback) => {
      modalElement.addEventListener("modalbeforeopen", callback);
    },
    beforeClose: (callback) => {
      modalElement.addEventListener("modalbeforeclose", callback);
    },
  };
}

export function md5(string) {
  function RotateLeft(lValue, iShiftBits) {
    return (lValue << iShiftBits) | (lValue >>> (32 - iShiftBits));
  }

  function AddUnsigned(lX, lY) {
    var lX4, lY4, lX8, lY8, lResult;
    lX8 = lX & 0x80000000;
    lY8 = lY & 0x80000000;
    lX4 = lX & 0x40000000;
    lY4 = lY & 0x40000000;
    lResult = (lX & 0x3fffffff) + (lY & 0x3fffffff);
    if (lX4 & lY4) {
      return lResult ^ 0x80000000 ^ lX8 ^ lY8;
    }
    if (lX4 | lY4) {
      if (lResult & 0x40000000) {
        return lResult ^ 0xc0000000 ^ lX8 ^ lY8;
      } else {
        return lResult ^ 0x40000000 ^ lX8 ^ lY8;
      }
    } else {
      return lResult ^ lX8 ^ lY8;
    }
  }

  function F(x, y, z) {
    return (x & y) | (~x & z);
  }
  function G(x, y, z) {
    return (x & z) | (y & ~z);
  }
  function H(x, y, z) {
    return x ^ y ^ z;
  }
  function I(x, y, z) {
    return y ^ (x | ~z);
  }

  function FF(a, b, c, d, x, s, ac) {
    a = AddUnsigned(a, AddUnsigned(AddUnsigned(F(b, c, d), x), ac));
    return AddUnsigned(RotateLeft(a, s), b);
  }

  function GG(a, b, c, d, x, s, ac) {
    a = AddUnsigned(a, AddUnsigned(AddUnsigned(G(b, c, d), x), ac));
    return AddUnsigned(RotateLeft(a, s), b);
  }

  function HH(a, b, c, d, x, s, ac) {
    a = AddUnsigned(a, AddUnsigned(AddUnsigned(H(b, c, d), x), ac));
    return AddUnsigned(RotateLeft(a, s), b);
  }

  function II(a, b, c, d, x, s, ac) {
    a = AddUnsigned(a, AddUnsigned(AddUnsigned(I(b, c, d), x), ac));
    return AddUnsigned(RotateLeft(a, s), b);
  }

  function ConvertToWordArray(string) {
    var lWordCount;
    var lMessageLength = string.length;
    var lNumberOfWords_temp1 = lMessageLength + 8;
    var lNumberOfWords_temp2 =
      (lNumberOfWords_temp1 - (lNumberOfWords_temp1 % 64)) / 64;
    var lNumberOfWords = (lNumberOfWords_temp2 + 1) * 16;
    var lWordArray = Array(lNumberOfWords - 1);
    var lBytePosition = 0;
    var lByteCount = 0;
    while (lByteCount < lMessageLength) {
      lWordCount = (lByteCount - (lByteCount % 4)) / 4;
      lBytePosition = (lByteCount % 4) * 8;
      lWordArray[lWordCount] =
        lWordArray[lWordCount] |
        (string.charCodeAt(lByteCount) << lBytePosition);
      lByteCount++;
    }
    lWordCount = (lByteCount - (lByteCount % 4)) / 4;
    lBytePosition = (lByteCount % 4) * 8;
    lWordArray[lWordCount] = lWordArray[lWordCount] | (0x80 << lBytePosition);
    lWordArray[lNumberOfWords - 2] = lMessageLength << 3;
    lWordArray[lNumberOfWords - 1] = lMessageLength >>> 29;
    return lWordArray;
  }

  function WordToHex(lValue) {
    var WordToHexValue = "",
      WordToHexValue_temp = "",
      lByte,
      lCount;
    for (lCount = 0; lCount <= 3; lCount++) {
      lByte = (lValue >>> (lCount * 8)) & 255;
      WordToHexValue_temp = "0" + lByte.toString(16);
      WordToHexValue =
        WordToHexValue +
        WordToHexValue_temp.substr(WordToHexValue_temp.length - 2, 2);
    }
    return WordToHexValue;
  }

  function Utf8Encode(string) {
    string = string.replace(/\r\n/g, "\n");
    var utftext = "";

    for (var n = 0; n < string.length; n++) {
      var c = string.charCodeAt(n);
      if (c < 128) {
        utftext += String.fromCharCode(c);
      } else if (c > 127 && c < 2048) {
        utftext += String.fromCharCode((c >> 6) | 192);
        utftext += String.fromCharCode((c & 63) | 128);
      } else {
        utftext += String.fromCharCode((c >> 12) | 224);
        utftext += String.fromCharCode(((c >> 6) & 63) | 128);
        utftext += String.fromCharCode((c & 63) | 128);
      }
    }

    return utftext;
  }

  var x = Array();
  var k, AA, BB, CC, DD, a, b, c, d;
  var S11 = 7,
    S12 = 12,
    S13 = 17,
    S14 = 22;
  var S21 = 5,
    S22 = 9,
    S23 = 14,
    S24 = 20;
  var S31 = 4,
    S32 = 11,
    S33 = 16,
    S34 = 23;
  var S41 = 6,
    S42 = 10,
    S43 = 15,
    S44 = 21;

  string = Utf8Encode(string);

  x = ConvertToWordArray(string);

  a = 0x67452301;
  b = 0xefcdab89;
  c = 0x98badcfe;
  d = 0x10325476;

  for (k = 0; k < x.length; k += 16) {
    AA = a;
    BB = b;
    CC = c;
    DD = d;
    a = FF(a, b, c, d, x[k + 0], S11, 0xd76aa478);
    d = FF(d, a, b, c, x[k + 1], S12, 0xe8c7b756);
    c = FF(c, d, a, b, x[k + 2], S13, 0x242070db);
    b = FF(b, c, d, a, x[k + 3], S14, 0xc1bdceee);
    a = FF(a, b, c, d, x[k + 4], S11, 0xf57c0faf);
    d = FF(d, a, b, c, x[k + 5], S12, 0x4787c62a);
    c = FF(c, d, a, b, x[k + 6], S13, 0xa8304613);
    b = FF(b, c, d, a, x[k + 7], S14, 0xfd469501);
    a = FF(a, b, c, d, x[k + 8], S11, 0x698098d8);
    d = FF(d, a, b, c, x[k + 9], S12, 0x8b44f7af);
    c = FF(c, d, a, b, x[k + 10], S13, 0xffff5bb1);
    b = FF(b, c, d, a, x[k + 11], S14, 0x895cd7be);
    a = FF(a, b, c, d, x[k + 12], S11, 0x6b901122);
    d = FF(d, a, b, c, x[k + 13], S12, 0xfd987193);
    c = FF(c, d, a, b, x[k + 14], S13, 0xa679438e);
    b = FF(b, c, d, a, x[k + 15], S14, 0x49b40821);
    a = GG(a, b, c, d, x[k + 1], S21, 0xf61e2562);
    d = GG(d, a, b, c, x[k + 6], S22, 0xc040b340);
    c = GG(c, d, a, b, x[k + 11], S23, 0x265e5a51);
    b = GG(b, c, d, a, x[k + 0], S24, 0xe9b6c7aa);
    a = GG(a, b, c, d, x[k + 5], S21, 0xd62f105d);
    d = GG(d, a, b, c, x[k + 10], S22, 0x02441453);
    c = GG(c, d, a, b, x[k + 15], S23, 0xd8a1e681);
    b = GG(b, c, d, a, x[k + 4], S24, 0xe7d3fbc8);
    a = GG(a, b, c, d, x[k + 9], S21, 0x21e1cde6);
    d = GG(d, a, b, c, x[k + 14], S22, 0xc33707d6);
    c = GG(c, d, a, b, x[k + 3], S23, 0xf4d50d87);
    b = GG(b, c, d, a, x[k + 8], S24, 0x455a14ed);
    a = GG(a, b, c, d, x[k + 13], S21, 0xa9e3e905);
    d = GG(d, a, b, c, x[k + 2], S22, 0xfcefa3f8);
    c = GG(c, d, a, b, x[k + 7], S23, 0x676f02d9);
    b = GG(b, c, d, a, x[k + 12], S24, 0x8d2a4c8a);
    a = HH(a, b, c, d, x[k + 5], S31, 0xfffa3942);
    d = HH(d, a, b, c, x[k + 8], S32, 0x8771f681);
    c = HH(c, d, a, b, x[k + 11], S33, 0x6d9d6122);
    b = HH(b, c, d, a, x[k + 14], S34, 0xfde5380c);
    a = HH(a, b, c, d, x[k + 1], S31, 0xa4beea44);
    d = HH(d, a, b, c, x[k + 4], S32, 0x4bdecfa9);
    c = HH(c, d, a, b, x[k + 7], S33, 0xf6bb4b60);
    b = HH(b, c, d, a, x[k + 10], S34, 0xbebfbc70);
    a = HH(a, b, c, d, x[k + 13], S31, 0x289b7ec6);
    d = HH(d, a, b, c, x[k + 0], S32, 0xeaa127fa);
    c = HH(c, d, a, b, x[k + 3], S33, 0xd4ef3085);
    b = HH(b, c, d, a, x[k + 6], S34, 0x04881d05);
    a = HH(a, b, c, d, x[k + 9], S31, 0xd9d4d039);
    d = HH(d, a, b, c, x[k + 12], S32, 0xe6db99e5);
    c = HH(c, d, a, b, x[k + 15], S33, 0x1fa27cf8);
    b = HH(b, c, d, a, x[k + 2], S34, 0xc4ac5665);
    a = II(a, b, c, d, x[k + 0], S41, 0xf4292244);
    d = II(d, a, b, c, x[k + 7], S42, 0x432aff97);
    c = II(c, d, a, b, x[k + 14], S43, 0xab9423a7);
    b = II(b, c, d, a, x[k + 5], S44, 0xfc93a039);
    a = II(a, b, c, d, x[k + 12], S41, 0x655b59c3);
    d = II(d, a, b, c, x[k + 3], S42, 0x8f0ccc92);
    c = II(c, d, a, b, x[k + 10], S43, 0xffeff47d);
    b = II(b, c, d, a, x[k + 1], S44, 0x85845dd1);
    a = II(a, b, c, d, x[k + 8], S41, 0x6fa87e4f);
    d = II(d, a, b, c, x[k + 15], S42, 0xfe2ce6e0);
    c = II(c, d, a, b, x[k + 6], S43, 0xa3014314);
    b = II(b, c, d, a, x[k + 13], S44, 0x4e0811a1);
    a = II(a, b, c, d, x[k + 4], S41, 0xf7537e82);
    d = II(d, a, b, c, x[k + 11], S42, 0xbd3af235);
    c = II(c, d, a, b, x[k + 2], S43, 0x2ad7d2bb);
    b = II(b, c, d, a, x[k + 9], S44, 0xeb86d391);
    a = AddUnsigned(a, AA);
    d = AddUnsigned(d, DD);
    c = AddUnsigned(c, CC);
    b = AddUnsigned(b, BB);
  }
  var temp = WordToHex(a) + WordToHex(d) + WordToHex(c) + WordToHex(b);
  return temp.toUpperCase();
}

export function md5Str(input) {
  const hash = md5(input);
  const codes = [];
  // Membagi hash menjadi 5 bagian yang sama (6 karakter)
  for (let i = 0; i < 5; i++) {
    codes.push(hash.substr(i * 6, 6));
  }
  return codes.join("-");
}
// Fungsi untuk melakukan HTTP request dengan fitur retry, timeout, dan validasi
export function BriefStorage(element) {
  // Konstanta untuk timeout
  const TIMEOUT_DURATION = 5000;
  const MAX_RETRIES = 3;
  const RETRY_DELAY = 1000;

  // Validasi URL
  const validateUrl = (url) => {
    try {
      new URL(url);
      return true;
    } catch {
      throw new Error("URL tidak valid");
    }
  };

  // Rate limiting
  const rateLimiter = {
    lastCall: 0,
    minInterval: 5, // 100ms antara request
    checkLimit() {
      const now = Date.now();
      if (now - this.lastCall < this.minInterval) {
        throw new Error("Terlalu banyak request. Mohon tunggu sebentar.");
      }
      this.lastCall = now;
    },
  };

  // Fungsi helper untuk setup request
  const setupRequest = (method, data = null) => {
    const controller = new AbortController();
    const config = {
      method,
      headers: {
        "Content-Type": "application/json",
        Accept: "application/json",
      },
      signal: controller.signal,
    };
    if (data) {
      if (typeof data !== "object") {
        throw new Error("Data harus berupa object");
      }
      config.body = JSON.stringify(data);
    }
    return { controller, config };
  };

  // Fungsi retry
  const retry = async (fn, retries = MAX_RETRIES) => {
    try {
      return await fn();
    } catch (error) {
      if (retries <= 1) throw error;
      await new Promise((resolve) => setTimeout(resolve, RETRY_DELAY));
      console.log(`Mencoba kembali... Sisa percobaan: ${retries - 1}`);
      return retry(fn, retries - 1);
    }
  };

  async function getData(url) {
    validateUrl(url);
    rateLimiter.checkLimit();

    const { controller, config } = setupRequest("GET");
    const timeoutId = setTimeout(() => controller.abort(), TIMEOUT_DURATION);

    return retry(async () => {
      try {
        const response = await fetch(url, config);
        clearTimeout(timeoutId);

        if (!response.ok) {
          throw {
            type: "HTTPError",
            status: response.status,
            message: `HTTP error! status: ${response.status}`,
            timestamp: new Date().toISOString(),
            url,
          };
        }

        const data = await response.json();
        console.log({
          type: "Success",
          method: "GET",
          url,
          timestamp: new Date().toISOString(),
        });
        return data;
      } catch (error) {
        clearTimeout(timeoutId);
        // console.error({
        //   type: "Error",
        //   method: "GET",
        //   url,
        //   error: error.message,
        //   timestamp: new Date().toISOString(),
        // });
        throw error;
      }
    });
  }

  async function sdk(url, data) {
    // const cookieManager = cookies();
    // const userCookie = cookieManager.get('HOST'); // returns 'john'
    const bseURI = app.url + "/sdk/" + url;
    validateUrl(bseURI);
    rateLimiter.checkLimit();

    const { controller, config } = setupRequest("POST", data);
    const timeoutId = setTimeout(() => controller.abort(), TIMEOUT_DURATION);

    return retry(async () => {
      try {
        const response = await fetch(bseURI, config);
        clearTimeout(timeoutId);

        if (!response.ok) {
          throw {
            type: "HTTPError",
            status: response.status,
            message: `HTTP error! status: ${response.status}`,
            timestamp: new Date().toISOString(),
            url,
            data,
          };
        }

        const result = await response.json();
        return result;
      } catch (error) {
        clearTimeout(timeoutId);
        console.error({
          type: "Error",
          method: "POST",
          url,
          error: error.message,
          data,
          timestamp: new Date().toISOString(),
        });
        throw error;
      }
    });
  }
  return { element, getData, sdk };
}

export async function Brief(row) {
  try {
    if (!row || !row.endpoint) {
      throw new Error("Parameter row dan endpoint diperlukan");
    }

    const briefInstance = BriefStorage(row);
    const data = await briefInstance.sdk(row.endpoint, row);
    return data;
  } catch (error) {
    console.error("Error dalam Brief:", error);
    throw error;
  }
}

export function Queue(row) {
  return {
    add: async function () {
      try {
        if (!row || !row.endpoint) {
          throw new Error("Parameter  dan endpoint diperlukan");
        }

        const briefInstance = BriefStorage(row);
        const data = await briefInstance.sdk(row.endpoint, row);
        return data;
      } catch (error) {
        console.error("Error dalam Queue:", error);
        throw error;
      }
    },
    up: async function (id) {
      try {
        if (!row || !row.endpoint) {
          throw new Error("Parameter  dan endpoint diperlukan");
        }
        const gabungArray = { ...row, id: id };
        const briefInstance = BriefStorage(row);
        const data = await briefInstance.sdk(row.endpoint, gabungArray);
        return { ...row.payload, id: id, data };
      } catch (error) {
        console.error("Error dalam Queue:", error);
        throw error;
      }
    },
    get: async function (id) {
      try {
        if (!row || !row) {
          throw new Error("Parameter  dan endpoint diperlukan");
        }
        const briefInstance = BriefStorage(row);
        const data = await briefInstance.sdk(row, { id: id });
        return data;
      } catch (error) {
        console.error("Error dalam Queue:", error);
        throw error;
      }
    },
    view: async function () {
      try {
        if (!row || !row.endpoint) {
          throw new Error("Parameter  dan endpoint diperlukan");
        }
        const briefInstance = BriefStorage(row);
        const data = await briefInstance.sdk(row.endpoint, row);
        return data;
      } catch (error) {
        console.error("Error dalam Queue:", error);
        throw error;
      }
    },
    del: async function (id) {
      try {
        if (!row || !row) {
          throw new Error("Parameter  dan endpoint diperlukan");
        }
        const briefInstance = BriefStorage(row);
        const data = await briefInstance.sdk(row, { id: id });
        return data;
      } catch (error) {
        console.error("Error dalam Queue:", error);
        throw error;
      }
    },
  };
}

// COMPONENE
/**
 * @class TDSDOM
 * @description Kelas untuk manajemen DOM dan template
 */
class TDSDOM {
  constructor() {
    /**
     * Helper function untuk escape karakter regex
     * @param {string} string - String yang akan di-escape
     * @returns {string} String yang sudah di-escape
     */
    const escapeRegExp = (string) => {
      return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    };

    /**
     * Render template dengan data
     * @param {string} template - Template string
     * @param {Object} data - Data untuk dirender
     * @param {Element} element - Element template
     */
    this.render = function(template, data, element) {
      try {
        // Validasi input
        if (!template || typeof template !== 'string') {
          throw new Error('Template harus berupa string');
        }
        if (!data || typeof data !== 'object') {
          throw new Error('Data harus berupa object');
        }

        let result = template;
        const dataKeys = Object.keys(data);

     
        // Proses setiap key data
        dataKeys.forEach(key => {
          const items = data[key];
          if (!Array.isArray(items)) {
            console.warn(`Data untuk key ${key} bukan array:`, items);
            return;
          }

          // Pattern untuk mencari tag template
          // Support multiple format tags termasuk Mustache-style
          const startTags = [
            `{@${key}}`, 
            `[${key}]`,
            `[@${key}]`,
            `<!--${key}-->`,
            `{{${key}}}`,     // Mustache-style
            `{{#${key}}}`,    // Mustache block
            `{{{${key}}}`,    // Mustache unescaped
            `{$${key}}`,      // PHP-style variable
            `{#${key}}`,      // Hash-style variable
            `\${${key}}`      // Template literal style
          ];  
          const endTags = [
            `{/${key}}`,
            `[/${key}]`,
            `[/${key}]`,
            `<!--/${key}-->`,
            `{{/${key}}}`,    // Mustache-style
            `{{/${key}}}`,    // Mustache block
            `{{{/${key}}}`,   // Mustache unescaped
            `{/${key}}`,      // PHP-style closing
            `{/${key}}`,      // Hash-style closing
            `\${/${key}}`     // Template literal style closing
          ];

          // Debug log
     

          // Cek format yang digunakan
          let templateStart = -1;
          let templateEnd = -1;
          let usedStartTag = '';
          let usedEndTag = '';
          let tagFound = false;

          // Cek format mana yang digunakan dengan case insensitive
          for(let i = 0; i < startTags.length; i++) {
            const startTagRegex = new RegExp(escapeRegExp(startTags[i]), 'i');
            const startMatch = result.match(startTagRegex);
            
            if(startMatch) {
              templateStart = startMatch.index;
              usedStartTag = startMatch[0];
              
              const endTagRegex = new RegExp(escapeRegExp(endTags[i]), 'i');
              const remainingContent = result.slice(templateStart + usedStartTag.length);
              const endMatch = remainingContent.match(endTagRegex);
              
              if(endMatch) {
                templateEnd = templateStart + usedStartTag.length + endMatch.index;
                usedEndTag = endMatch[0];
                tagFound = true;
                //console.debug(`Tag ditemukan: ${usedStartTag} ... ${usedEndTag}`);
                break;
              }
            }
          }
          
          if (!tagFound) {
            console.warn(`Tag template untuk "${key}" tidak ditemukan dalam template`);
            console.warn('Template yang tersedia:', result);
            return;
          }

          const itemTemplate = result.substring(
            templateStart + usedStartTag.length,
            templateEnd
          );

          // console.debug(`Template item untuk "${key}":`, itemTemplate);

          // Render setiap item dengan dukungan Mustache yang lebih baik
          let renderedItems = items.map(item => {
            let itemResult = itemTemplate;
            
            // Replace semua placeholder dengan nilai item
            Object.keys(item).forEach(prop => {
              const value = item[prop] ?? '';
              // Support multiple format placeholders termasuk Mustache
              const patterns = [
                new RegExp(`{${prop}}`, 'g'),
                new RegExp(`\\[${prop}\\]`, 'g'),
                new RegExp(`<!--${prop}-->`, 'g'),
                new RegExp(`{{${prop}}}`, 'g'),       // Mustache escaped
                new RegExp(`{{{${prop}}}}`, 'g'),     // Mustache unescaped
                new RegExp(`{{&${prop}}}`, 'g'),      // Mustache unescaped alternative
                new RegExp(`{\\$${prop}}`, 'g'),      // PHP-style variable
                new RegExp(`{#${prop}}`, 'g'),         // Hash-style variable
                new RegExp(`\\$\\{${prop}\\}`, 'g')   // Template literal style
              ];
              
              patterns.forEach(pattern => {
                // Untuk format Mustache escaped, escape HTML
                if (pattern.toString().includes('{{') && !pattern.toString().includes('{{{')) {
                  itemResult = itemResult.replace(pattern, this.sanitize(String(value)));
                } else {
                  itemResult = itemResult.replace(pattern, String(value));
                }
              });
            });
            
            return itemResult;
          }).join('');

          // Replace template dengan hasil render
          result = result.replace(
            `${usedStartTag}${itemTemplate}${usedEndTag}`,
            renderedItems
          );
        });

        // Debug log hasil akhir
 
        return result;

      } catch (error) {
        console.error('Error dalam render:', error);
        console.error('Template:', template);
        console.error('Data:', data);
        return '';
      }
    };

    /**
     * Parse string template menjadi DOM elements
     * @param {string} template - Template string
     * @returns {DocumentFragment}
     */
    this.parse = function(template) {
      const parser = new DOMParser();
      const doc = parser.parseFromString(template, 'text/html');
      const fragment = document.createDocumentFragment();
      
      while (doc.body.firstChild) {
        fragment.appendChild(doc.body.firstChild);
      }
      
      return fragment;
    };

    /**
     * Sanitize string untuk mencegah XSS
     * @param {string} str - String yang akan disanitize
     * @returns {string}
     */
    this.sanitize = function(str) {
      const div = document.createElement('div');
      div.textContent = str;
      return div.innerHTML;
    };
  }
}
// SPA
export class SinglePageApp {
  constructor() {
    this.dbName = 'spaCache';
    this.storeName = 'responses';
    this.db = null;
    this.loadingHTML = `
      <div class="loading">
        <div class="spinner"></div>
        <p>Memuat konten...</p>
      </div>
    `;
    this.retryConfig = {
      maxRetries: 3,
      retryDelay: 1000,
      backoffMultiplier: 1.5
    };
  }

  async initDB() {
    return new Promise((resolve, reject) => {
      const request = indexedDB.open(this.dbName, 1);
      
      request.onerror = () => reject(request.error);
      request.onsuccess = () => {
        this.db = request.result;
        resolve(this.db);
      };
      
      request.onupgradeneeded = (event) => {
        const db = event.target.result;
        if (!db.objectStoreNames.contains(this.storeName)) {
          const store = db.createObjectStore(this.storeName, { keyPath: 'id' });
          store.createIndex('timestamp', 'timestamp', { unique: false });
        }
      };
    });
  }

  isCryptoSupported() {
    return window.crypto && window.crypto.subtle;
  }

  async getEncryptionKey(endpoint) {
    const keyData = new TextEncoder().encode(endpoint || 'default-endpoint-key');
    return await crypto.subtle.importKey(
      'raw',
      keyData,
      'AES-GCM',
      false,
      ['encrypt', 'decrypt']
    );
  }

  async encryptData(data, endpoint) {
    if (!this.isCryptoSupported()) {
      return { raw: true, data };
    }

    try {
      const key = await this.getEncryptionKey(endpoint);
      const iv = crypto.getRandomValues(new Uint8Array(12));
      const encodedData = new TextEncoder().encode(data);
      
      const encryptedData = await crypto.subtle.encrypt(
        { name: 'AES-GCM', iv },
        key,
        encodedData
      );

      return {
        iv: Array.from(iv),
        data: Array.from(new Uint8Array(encryptedData))
      };
    } catch (error) {
      console.warn('Enkripsi gagal:', error);
      return { raw: true, data };
    }
  }

  async decryptData(encryptedObj, endpoint) {
    if (encryptedObj.raw) {
      return encryptedObj.data;
    }

    try {
      const key = await this.getEncryptionKey(endpoint);
      const decrypted = await crypto.subtle.decrypt(
        { name: 'AES-GCM', iv: new Uint8Array(encryptedObj.iv) },
        key,
        new Uint8Array(encryptedObj.data)
      );

      return new TextDecoder().decode(decrypted);
    } catch (error) {
      console.warn('Dekripsi gagal:', error);
      throw error;
    }
  }

  async saveToCache(key, data, endpoint) {
    const transaction = this.db.transaction([this.storeName], 'readwrite');
    const store = transaction.objectStore(this.storeName);
    
    try {
      const objToEncrypt = JSON.stringify({
        data: data,
        timestamp: new Date().getTime()
      });
      const encryptedData = await this.encryptData(objToEncrypt, endpoint);
      
      await store.put({
        id: key,
        encryptedContent: encryptedData
      });
    } catch (error) {
      console.warn('Cache write error:', error);
    }
  }

  async getFromCache(key) {
    const transaction = this.db.transaction([this.storeName], 'readonly');
    const store = transaction.objectStore(this.storeName);
    
    return new Promise(async (resolve, reject) => {
      try {
        const request = store.get(key);
        request.onsuccess = async () => {
          if (!request.result) {
            resolve(null);
            return;
          }
          
          const decryptedStr = await this.decryptData(request.result.encryptedContent);
          const decryptedObj = JSON.parse(decryptedStr);
          
          resolve({
            id: key,
            data: decryptedObj.data,
            timestamp: decryptedObj.timestamp
          });
        };
        request.onerror = () => reject(request.error);
      } catch (error) {
        console.warn('Cache read error:', error);
        reject(error);
      }
    });
  }

  async clearOldCache() {
    const maxAge = 7 * 24 * 60 * 60 * 1000; // 7 hari
    const now = new Date().getTime();
    
    const transaction = this.db.transaction([this.storeName], 'readwrite');
    const store = transaction.objectStore(this.storeName);
    const index = store.index('timestamp');
    
    const range = IDBKeyRange.upperBound(now - maxAge);
    index.openCursor(range).onsuccess = (event) => {
      const cursor = event.target.result;
      if (cursor) {
        store.delete(cursor.primaryKey);
        cursor.continue();
      }
    };
  }

  delay(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
  }

  async fetchWithRetry(url, options, retryCount = 0) {
    try {
      const response = await fetch(url, options);
      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }
      return response;
    } catch (error) {
      if (retryCount >= this.retryConfig.maxRetries) {
        throw new Error(`Gagal setelah ${this.retryConfig.maxRetries} percobaan: ${error.message}`);
      }

      const waitTime = this.retryConfig.retryDelay * Math.pow(this.retryConfig.backoffMultiplier, retryCount);
      console.warn(`Percobaan ke-${retryCount + 1} gagal. Mencoba ulang dalam ${waitTime}ms...`);
      
      await this.delay(waitTime);
      return this.fetchWithRetry(url, options, retryCount + 1);
    }
  }

  async SinglePageApplication(e) {
    const encodedData = btoa(JSON.stringify(e));
    const State = {
    url: "https://" + e.endpoint,
    data: e.data || e,
    elementById: e.elementById,
    encodedData: encodedData,
    endpoint: e.endpoint
  }

    if (!this.db) {
      await this.initDB();
    }
    
    const contentElement = document.getElementById(State.elementById);
    contentElement.innerHTML = this.loadingHTML;

    const cacheKey = md5Str(State.encodedData + '_v1');
    
    try {
      const cachedData = await this.getFromCache(cacheKey);
      if (cachedData) {
        contentElement.innerHTML = cachedData.data;
        return cachedData.data;
      }
    } catch (error) {
      console.warn('Cache read error:', error);
    }

    try {
      const controller = new AbortController();
      const timeoutId = setTimeout(() => controller.abort(), 5000);

      const response = await this.fetchWithRetry(app.url + "/worker/" + cacheKey, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({
          ...State.data,
          key: md5Str(State.url) || "",
          brief: State.url || "",
          pageparser:window.location.href,
          timestamp: new Date().getTime(),
        }),
        signal: controller.signal,
      });

      clearTimeout(timeoutId);
      
      const responseData = await response.text();
      
      try {
        await this.saveToCache(cacheKey, responseData, State.endpoint);
        await this.clearOldCache();
      } catch (e) {
        console.warn('Cache write error:', e);
      }
      
      contentElement.innerHTML = responseData;
      return responseData;
    } catch (error) {
      const errorMessage = error.message.includes('Gagal setelah') 
        ? error.message 
        : `Error: ${error.message}`;
      
      contentElement.innerHTML = `<div class="error">${errorMessage}</div>`;
      return contentElement.innerHTML;
    }
  }
}

// Inisialisasi Worker
const worker = new Worker(app.url + "/js/Worker.js");

// Fungsi untuk menggunakan Worker
const useWorker = (action, data) => {
  return new Promise((resolve, reject) => {
    worker.onmessage = (e) => {
      const { action: responseAction, result, error } = e.data;
      if (responseAction === `${action}Complete`) {
        resolve(result);
      } else if (responseAction === 'error') {
        reject(new Error(error));
      }
    };

    worker.onerror = (error) => {
      reject(error);
    };

    worker.postMessage({ action, data });
  });
};

// Contoh penggunaan Worker untuk filter
async function filterItems(items, filters) {
  try {
    const filteredItems = await useWorker('filter', { items, filters });
    console.log('Filtered items:', filteredItems);
    return filteredItems;
  } catch (error) {
    console.error('Error filtering items:', error);
  }
}

// Contoh penggunaan Worker untuk search
async function searchItems(items, query, searchFields) {
  try {
    const searchResults = await useWorker('search', { items, query, searchFields });
    console.log('Search results:', searchResults);
    return searchResults;
  } catch (error) {
    console.error('Error searching items:', error);
  }
}

export async function latSinglePageApp(e) {
    // Inisialisasi Worker $dataset['pageparser']
  
    const worker = new Worker(app.url + "/js/Worker.js");
    const encodedData = btoa(JSON.stringify(e));
    const State = {
    url: "https://" + e.endpoint,
    data: e.data || e,
    elementById: e.elementById,
    encodedData: encodedData,
    endpoint: e.endpoint
  }
    try { 
        const response = await fetch(app.url + "/worker/" + md5Str(encodedData), {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
            },
            body: JSON.stringify({
                ...State.data,
                key: md5Str(State.url) || "",
                brief: State.url || "",
                pageparser:window.location.href,
                timestamp: new Date().getTime(),
            }),
        });

        return await response.text();
    } catch (error) {
        console.error('Error:', error);
        return null;
    } finally {
        // Terminate worker setelah selesai
        worker.terminate();
    }
}

// AND SPA
export function Encode(argument) {
  if (!argument) {
    throw new Error('Input tidak boleh kosong');
  }

  try {
    // Konversi input ke string jika bukan string
    const inputString = typeof argument === 'string' 
      ? argument 
      : JSON.stringify(argument);
    
    // Encode ke Base64 dan buat URL-safe
    const encodedString = btoa(inputString)
      .replace(/\+/g, "-")
      .replace(/\//g, "_")
      .replace(/=+$/, "");
    
    return encodedString;
  } catch (error) {
    throw new Error(`Gagal mengencode data: ${error.message}`);
  }
}

// URL-safe Base64 Decode
export function Decode(argument) {
  if (!argument || typeof argument !== 'string') {
    throw new Error('Input harus berupa string');
  }

  try {
    // Konversi ke format Base64 standar
    const paddedString = argument.replace(/-/g, "+").replace(/_/g, "/");
    const padding = (4 - (paddedString.length % 4)) % 4;
    const base64String = padding > 0 ? paddedString + "=".repeat(padding) : paddedString;

    // Decode Base64
    const decodedString = atob(base64String);

    // Bersihkan dan parse JSON
    const cleanedData = decodedString
      .replace(/'/g, '"')
      .replace(/([{,]\s*)(\w+):/g, '$1"$2":');

    return JSON.parse(cleanedData);
  } catch (error) {
    throw new Error(`Gagal mendecode data: ${error.message}`);
  }
}

//Tabel Matrix
export class TabelMatrix {
    constructor(options) {
        this.options = options;
        this.currentPage = 1;
        this.paginationId = options.pagination || 'pagination';
        this.paginationPosition = options.paginationPosition || 'center';
        this.searchId = options.search;
        this.exportOptions = options.export || {};
        
        // Pastikan data tersedia sebelum menyalin
        if (options.data) {
            this.data = {
                columns: [...(options.data.columns || [])],
                data: options.data.data ? JSON.parse(JSON.stringify(options.data.data)) : []
            };
            
            this.originalData = {
                columns: [...(options.data.columns || [])],
                data: options.data.data ? JSON.parse(JSON.stringify(options.data.data)) : []
            };
        } else {
            this.data = { columns: [], data: [] };
            this.originalData = { columns: [], data: [] };
        }

        this.virtualScrolling = options.virtualScrolling || false;
        this.chunkSize = options.chunkSize || 100;
        this.bufferSize = options.bufferSize || 50;
        this.rowHeight = options.rowHeight || 40;
        this.memoryLimit = options.memoryLimit || 100000;
        this.visibleRows = [];
        this.scrollTop = 0;
        this.lastScrollTop = 0;
        this.scrollThrottle = null;
        this.renderRequestId = null;
        this.isScrolling = false;
        
        this.requiredLibraries = {
            xlsx: {
                name: 'XLSX',
                url: 'https://cdn.jsdelivr.net/npm/xlsx/dist/xlsx.full.min.js'
            },
            pdf: {
                name: 'jspdf',
                url: 'https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js'
            },
            autoTable: {
                name: 'jspdf-autotable',
                url: 'https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.28/jspdf.plugin.autotable.min.js',
                depends: 'pdf'
            }
        };
        
        this.headerStyles = {
            backgroundColor: options.headerBackgroundColor || '#e5e9f2',
            color: options.headerTextColor || '#000',
            fontSize: options.headerFontSize || '14px',
            fontWeight: options.headerFontWeight || '700'
        };
        
        this.searchIndex = {};
        this.searchableColumns = options.searchableColumns || [];
        
        // Pindahkan buildSearchIndex setelah data diinisialisasi
        if (this.data && this.data.columns) {
            this.buildSearchIndex();
        }
        
        this.filters = options.filters || {};
        
        this.columnFormatters = options.columnFormatters || {};
        
        // Tambahkan properti baru untuk menangani kolom yang dapat diedit
        this.editableColumns = options.editableColumns || {};
        
        // Properti untuk menyimpan konfigurasi editor yang sedang aktif
        this.activeEditor = null;
        
        this.init();
        this.setupFilters();
        this.setupExportOptions();
    }

    init() {
        const tabelSet = document.getElementById(this.options.containerId);
        if (!tabelSet) return;

        this.data = this.options.data;
        this.perPage = this.options.perPage || 10;
        this.currentPage = 1;
        this.sortDirection = {};

        this.setupExportOptions();

        if (this.virtualScrolling) {
            //console.log('Virtual scrolling aktif');
            this.setupVirtualScrolling();
        } else {
            this.createTable();
        }

        this.setupSearch();
        if (!this.virtualScrolling) {
            this.createPagination();
        }
        this.setupExportButtons();
        this.setupErrorHandling();
    }

    setupErrorHandling() {
        window.onerror = (message, source, lineno, colno, error) => {
            // console.error('Terjadi kesalahan:', message, 'di', source, 'baris', lineno);
        };
    }

    sortTable(columnField) {
        const direction = this.sortDirection[columnField] || 'asc';
        const multiplier = direction === 'asc' ? 1 : -1;

        this.data.data.sort((a, b) => {
            if (a[columnField] < b[columnField]) return -1 * multiplier;
            if (a[columnField] > b[columnField]) return 1 * multiplier;
            return 0;
        });

        this.sortDirection[columnField] = direction === 'asc' ? 'desc' : 'asc';
        this.createTable();
    }

    createTable() {
        if (this.virtualScrolling) {
            if (!this.tableWrapper) return;
            this.tableWrapper.innerHTML = '';
            
            const table = this.createTableElement();
            const thead = this.createTableHeader();
            const tbody = document.createElement('tbody');
            
            table.appendChild(thead);
            table.appendChild(tbody);
            this.tableWrapper.appendChild(table);
            
            this.handleScroll(this.scrollWrapper);
            this.updateSortIcons();
        } else {
            this.clearContainer();
            const table = this.createTableElement();
            const thead = this.createTableHeader();
            const tbody = this.createTableBody();
            
            table.appendChild(thead);
            table.appendChild(tbody);
            
            this.appendTableToContainer(table);
            this.updateSortIcons();
            this.updatePaginationInfo();
        }
    }

    clearContainer() {
        const container = document.getElementById(this.options.containerId);
        container.innerHTML = "";
    }

    createTableElement() {
        const table = document.createElement('table');
        table.style = 'width:100%;';
        return table;
    }

    createTableHeader() {
        const thead = document.createElement('thead');
        const headerRow1 = document.createElement('tr');
        const headerRow2 = document.createElement('tr');
        
        const numberHeader = document.createElement('th');
        this.applyStyles(numberHeader, {
            backgroundColor: this.headerStyles.backgroundColor,
            color: this.headerStyles.color,
            fontSize: this.headerStyles.fontSize,
            fontWeight: this.headerStyles.fontWeight,
            textAlign: 'center',
            verticalAlign: 'middle',
            padding: '10px',
            border: '1px solid #ccc'
        });
        numberHeader.rowSpan = 2;
        numberHeader.innerHTML = 'No';
        headerRow1.appendChild(numberHeader);

        this.data.columns.forEach(column => {
            const th = document.createElement('th');
            this.applyStyles(th, {
                backgroundColor: this.headerStyles.backgroundColor,
                color: this.headerStyles.color,
                fontSize: this.headerStyles.fontSize,
                fontWeight: this.headerStyles.fontWeight,
                textAlign: 'center',
                verticalAlign: 'middle',
                padding: '10px',
                border: '1px solid #ccc',
                cursor: 'pointer'
            });

            if (column.columns) {
                th.colSpan = column.columns.filter(subCol => subCol.colum !== false).length;
                th.rowSpan = 1;
                th.innerHTML = `${column.title.split('\n')[0]} <span class="sort-icon  pull-right" data-field="${column.field}"></span>`;
                headerRow1.appendChild(th);

                column.columns.forEach(subCol => {
                    // Skip kolom jika colum adalah false
                    if (subCol.colum === false) return;

                    const subTh = document.createElement('th');
                    subTh.innerHTML = `${subCol.title.split('\n')[0]} <span class="sort-icon  pull-right" data-field="${subCol.field}"></span>`;
                    
                    // Tambahkan class jika ada
                    if (subCol.class) {
                        subTh.className = subCol.class;
                    }

                    this.applyStyles(subTh, {
                        backgroundColor: this.headerStyles.backgroundColor,
                        color: this.headerStyles.color,
                        fontSize: this.headerStyles.fontSize,
                        fontWeight: this.headerStyles.fontWeight,
                        textAlign: 'center',
                        verticalAlign: 'middle',
                        padding: '10px',
                        border: '1px solid #ccc',
                        cursor: 'pointer'
                    });
                    subTh.onclick = () => this.sortTable(subCol.field);
                    headerRow2.appendChild(subTh);
                });
            } else {
                th.rowSpan = 2;
                th.colSpan = 1;
                th.innerHTML = `${column.title.split('\n')[0]} <span class="sort-icon pull-right" data-field="${column.field}"></span>`;
                th.onclick = () => this.sortTable(column.field);
                headerRow1.appendChild(th);

                // Tambahkan class jika ada
                if (column.class) {
                    th.className = column.class;
                }
            }
        });

        thead.appendChild(headerRow1);
        thead.appendChild(headerRow2);
        return thead;
    }

    createTableBody() {
        const tbody = document.createElement('tbody');
        const startIndex = (this.currentPage - 1) * this.perPage;
        const endIndex = startIndex + this.perPage;
        const visibleData = this.data.data.slice(startIndex, endIndex);

        visibleData.forEach((rowData, index) => {
            const row = document.createElement('tr');
            
            // Sel nomor
            const numberCell = document.createElement('td');
            numberCell.innerText = startIndex + index + 1;
            this.applyStyles(numberCell, {
                textAlign: 'center',
                fontSize: '13px',
                fontWeight: '400',
                backgroundColor: '#ffffff',
                padding: '10px',
                border: '1px solid #ccc',
                verticalAlign: 'top'
            });
            row.appendChild(numberCell);

            // Iterasi kolom
            this.data.columns.forEach(column => {
                if (column.columns) {
                    column.columns.forEach(subColumn => {
                        // Skip kolom jika colum adalah false
                        if (subColumn.colum === false) return;
                        
                        const td = this.createDataCell(rowData, subColumn, index);
                        row.appendChild(td);
                    });
                } else {
                    const td = this.createDataCell(rowData, column, index);
                    row.appendChild(td);
                }
            });
            tbody.appendChild(row);
        });

        return tbody;
    }

    appendTableToContainer(table) {
        const container = document.getElementById(this.options.containerId);
        container.appendChild(table);
    }

    updateSortIcons() {
        const icons = document.querySelectorAll('.sort-icon');
        icons.forEach(icon => {
            const field = icon.getAttribute('data-field');
            icon.innerHTML = '';
            if (this.sortDirection[field]) {
                icon.innerHTML = this.sortDirection[field] === 'asc' ? ' <i class="icon-feather-chevron-up"></i>' : ' <i class="icon-feather-chevron-down"></i>';
            }
        });
    }

    setupSearch() {
        if (this.searchId) {
            const searchInput = document.getElementById(this.searchId);
            if (searchInput) {
                //console.log('Search input ditemukan:', this.searchId);
                searchInput.addEventListener('input', (e) => {
                    //console.log('Mencari:', e.target.value);
                    this.performSearch(e.target.value);
                });
            } else {
                console.warn(`Elemen pencarian dengan id "${this.searchId}" tidak ditemukan.`);
            }
        }
    }

    buildSearchIndex() {
        //console.log('Building search index for columns:', this.searchableColumns);
        
        // Pastikan data tersedia sebelum membangun index
        if (!this.data || !this.data.columns) {
            console.warn('Data atau kolom belum tersedia untuk search index');
            return;
        }
        
        if (!this.searchableColumns || this.searchableColumns.length === 0) {
            // Jika searchableColumns tidak ditentukan, indeks semua kolom
            this.searchableColumns = this.data.columns.reduce((acc, col) => {
                if (col.columns) {
                    return [...acc, ...col.columns.map(subCol => subCol.field)];
                }
                return [...acc, col.field];
            }, []);
        }

        // Reset search index
        this.searchIndex = {};

        // Buat indeks untuk setiap kolom yang dapat dicari
        this.searchableColumns.forEach(field => {
            this.searchIndex[field] = new Map();
            
            if (!this.data || !this.data.data) {
                console.warn('Data tidak tersedia untuk indexing');
                return;
            }

            this.data.data.forEach((row, idx) => {
                if (row[field] === undefined) {
                    console.warn(`Field "${field}" tidak ditemukan pada baris ${idx}`);
                    return;
                }

                const value = String(row[field]).toLowerCase();
                if (!this.searchIndex[field].has(value)) {
                    this.searchIndex[field].set(value, new Set());
                }
                this.searchIndex[field].get(value).add(idx);
            });
        });

        //console.log('Search index built:', this.searchIndex);
    }

    performSearch(searchText) {
        clearTimeout(this.searchTimeout);
        this.searchTimeout = setTimeout(() => {
            const startTime = performance.now();
            searchText = searchText.toLowerCase();

            if (searchText.length === 0) {
                this.data = {
                    ...this.originalData,
                    data: [...this.originalData.data]
                };
                this.refreshTable();
                return;
            }

            // Gunakan filter langsung untuk pencarian sederhana
            const filteredData = this.originalData.data.filter(row => {
                return this.searchableColumns.some(field => {
                    const value = String(row[field] || '').toLowerCase();
                    return value.includes(searchText);
                });
            });

            // Update data tabel dengan hasil pencarian
            this.data = {
                ...this.originalData,
                data: filteredData
            };

            const endTime = performance.now();
            //console.log(`Pencarian selesai dalam ${endTime - startTime}ms`);

            this.refreshTable();
        }, 300);
    }

    refreshTable() {
        this.currentPage = 1;
        if (this.virtualScrolling) {
            this.setupVirtualScrolling();
        } else {
            this.createTable();
            this.createPagination();
        }
    }

    // Tambahkan cache untuk hasil pencarian
    searchCache = new Map();
    
    performSearchWithCache(searchText) {
        clearTimeout(this.searchTimeout);
        this.searchTimeout = setTimeout(() => {
            searchText = searchText.toLowerCase();

            // Cek cache
            if (this.searchCache.has(searchText)) {
                this.data.data = this.searchCache.get(searchText);
                this.refreshTable();
                return;
            }

            // Lakukan pencarian
            const results = this.performSearch(searchText);
            
            // Simpan ke cache
            if (this.searchCache.size > 100) { // Batasi ukuran cache
                const firstKey = this.searchCache.keys().next().value;
                this.searchCache.delete(firstKey);
            }
            this.searchCache.set(searchText, results);

        }, 300);
    }

    // Tambahkan metode untuk pencarian fuzzy
    performFuzzySearch(searchText, threshold = 0.3) {
        return this.originalData.data.filter(row => {
            return this.searchableColumns.some(field => {
                const value = String(row[field]).toLowerCase();
                return this.calculateLevenshteinDistance(value, searchText.toLowerCase()) <= threshold;
            });
        });
    }

    calculateLevenshteinDistance(str1, str2) {
        const track = Array(str2.length + 1).fill(null).map(() =>
            Array(str1.length + 1).fill(null));
        
        for(let i = 0; i <= str1.length; i++) track[0][i] = i;
        for(let j = 0; j <= str2.length; j++) track[j][0] = j;
        
        for(let j = 1; j <= str2.length; j++) {
            for(let i = 1; i <= str1.length; i++) {
                const indicator = str1[i - 1] === str2[j - 1] ? 0 : 1;
                track[j][i] = Math.min(
                    track[j][i - 1] + 1,
                    track[j - 1][i] + 1,
                    track[j - 1][i - 1] + indicator
                );
            }
        }
        
        return track[str2.length][str1.length];
    }

    applyStyles(element, styles) {
        Object.keys(styles).forEach(style => {
            element.style[style] = styles[style];
        });
    }

    createPagination() {
        // Cek apakah paginasi dibutuhkan
        if (!this.paginationId || this.virtualScrolling) {
            return;
        }

        const paginationContainer = document.getElementById(this.paginationId);
        if (!paginationContainer) {
            console.warn(`Elemen paginasi dengan id "${this.paginationId}" tidak ditemukan, paginasi tidak akan ditampilkan.`);
            return;
        }

        // Hapus paginasi yang ada jika ada
        paginationContainer.innerHTML = '';

        // Jika tidak ada data, tidak perlu membuat paginasi
        if (!this.data || !this.data.data || this.data.data.length === 0) {
            return;
        }

        const paginationList = document.createElement('ul');
        paginationList.className = `pagination justify-content-${this.paginationPosition}`;

        paginationContainer.appendChild(paginationList);

        this.updatePaginationInfo(paginationList);
    }

    updatePaginationInfo(paginationList) {
        // Jika paginationList tidak diberikan, coba dapatkan dari container
        if (!paginationList && this.paginationId) {
            const paginationContainer = document.getElementById(this.paginationId);
            if (!paginationContainer) {
                return;
            }
            paginationList = paginationContainer.querySelector('.pagination');
            if (!paginationList) {
                return;
            }
        }

        // Jika masih tidak ada paginationList, keluar
        if (!paginationList) {
            return;
        }

        // Bersihkan paginasi yang ada
        paginationList.innerHTML = '';

        const totalPages = Math.ceil(this.data.data.length / this.perPage);
        if (totalPages <= 1) {
            return; // Tidak perlu paginasi jika hanya 1 halaman
        }

        const createPageItem = (text, pageNum, active = false, disabled = false) => {
            const li = document.createElement('li');
            li.className = `page-item ${active ? 'active' : ''} ${disabled ? 'disabled' : ''}`;
            const a = document.createElement('a');
            a.className = 'page-link';
            a.href = '#';
            a.innerText = text;
            if (!disabled) {
                a.addEventListener('click', (e) => {
                    e.preventDefault();
                    if (text === 'Previous') {
                        this.goToPage(this.currentPage - 1);
                    } else if (text === 'Next') {
                        this.goToPage(this.currentPage + 1);
                    } else if (text === 'Last') {
                        this.goToPage(totalPages);
                    } else if (text === 'First') {
                        this.goToPage(1);
                    } else {
                        this.goToPage(pageNum);
                    }
                });
            }
            li.appendChild(a);
            return li;
        };

        // Tambahkan tombol First
        paginationList.appendChild(createPageItem('First', 1, false, this.currentPage === 1));

        // Tambahkan tombol Previous
        paginationList.appendChild(createPageItem('Previous', this.currentPage - 1, false, this.currentPage === 1));

        // Tambahkan nomor halaman
        let startPage = Math.max(1, this.currentPage - 2);
        let endPage = Math.min(startPage + 4, totalPages);
        if (endPage - startPage < 4) {
            startPage = Math.max(1, endPage - 4);
        }

        for (let i = startPage; i <= endPage; i++) {
            paginationList.appendChild(createPageItem(i, i, i === this.currentPage));
        }

        // Tambahkan tombol Next
        paginationList.appendChild(createPageItem('Next', this.currentPage + 1, false, this.currentPage === totalPages));

        // Tambahkan tombol Last
        paginationList.appendChild(createPageItem('Last', totalPages, false, this.currentPage === totalPages));

        // Tambahkan info halaman
        const pageInfo = document.createElement('li');
        pageInfo.className = 'page-item disabled';
        pageInfo.innerHTML = `<span class="page-link">
            Halaman ${this.currentPage} dari ${totalPages}
            (Total: ${this.data.data.length} data)
        </span>`;
        paginationList.appendChild(pageInfo);
    }

    goToPage(pageNum) {
        const totalPages = Math.ceil(this.data.data.length / this.perPage);
        if (pageNum >= 1 && pageNum <= totalPages && pageNum !== this.currentPage) {
            this.currentPage = pageNum;
            this.createTable();
            this.updatePaginationInfo();
        }
    }

    nextPage() {
        this.goToPage(this.currentPage + 1);
    }

    prevPage() {
        this.goToPage(this.currentPage - 1);
    }

    setupExportButtons() {
        Object.entries(this.exportOptions).forEach(([format, buttonId]) => {
            const button = document.getElementById(buttonId);
            if (button) {
                button.textContent = format; // Set label tombol sesuai format
                button.addEventListener('click', () => {
                    this.exportData(format);
                });
            } else {
                console.warn(`Elemen ekspor dengan id "${buttonId}" tidak ditemukan.`);
            }
        });
    }

    async exportData(format) {
        const formatKey = format.toLowerCase();
        if (this.exportFormats[formatKey]) {
            try {
                // Load library yang diperlukan
                if (formatKey === 'xlsx') {
                    await this.loadLibrary('xlsx');
                } else if (formatKey === 'pdf') {
                    await this.loadLibrary('pdf');
                    await this.loadLibrary('autoTable');
                }
                
                this.exportFormats[formatKey].processor();
            } catch (error) {
                console.error(`Error saat export ${format}:`, error);
                this.triggerExportCallback('error', formatKey, error);
            }
        } else {
            console.warn(`Format ekspor "${format}" tidak dikenali.`);
        }
    }

    setupExportOptions() {
        this.exportFormats = {
            xlsx: {
                mimeType: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                extension: '.xlsx',
                processor: this.exportXLSX.bind(this)
            },
            pdf: {
                mimeType: 'application/pdf',
                extension: '.pdf',
                processor: this.exportPDF.bind(this)
            },
            csv: {
                mimeType: 'text/csv',
                extension: '.csv',
                processor: this.exportCSV.bind(this)
            },
            json: {
                mimeType: 'application/json',
                extension: '.json',
                processor: this.exportJSON.bind(this)
            }
        };
    }

    exportXLSX() {
        try {
            if (typeof XLSX === 'undefined') {
                throw new Error('Library XLSX tidak tersedia');
            }

            // Persiapkan data untuk export
            const exportData = this.prepareExportData();
            
            // Konfigurasi worksheet
            const ws = XLSX.utils.json_to_sheet(exportData.data);
            
            // Styling worksheet
            const wsStyles = {
                '!cols': exportData.columns.map(() => ({ wch: 15 })), // Auto width
                '!rows': [{ hpt: 25 }], // Header height
            };
            
            // Merge dengan style yang ada
            ws['!cols'] = wsStyles['!cols'];
            ws['!rows'] = wsStyles['!rows'];

            const wb = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(wb, ws, this.options.sheetName || "Sheet1");

            // Tambahkan metadata
            wb.Props = {
                Title: this.options.fileName || "Export Data",
                Author: "System",
                CreatedDate: new Date()
            };

            XLSX.writeFile(wb, `${this.options.fileName || 'export'}.xlsx`);
            
            this.triggerExportCallback('success', 'xlsx');
        } catch (error) {
            console.error('Error exporting XLSX:', error);
            this.triggerExportCallback('error', 'xlsx', error);
        }
    }

    async exportPDF() {
        try {
            if (typeof window.jspdf === 'undefined') {
                await this.loadLibrary('pdf');
                await this.loadLibrary('autoTable');
            }

            const { jsPDF } = window.jspdf;
            const doc = new jsPDF({
                orientation: this.options.pdfOrientation || 'landscape',
                unit: 'mm',
                format: this.options.pdfFormat || 'a4'
            });

            // Tambahkan header
            if (this.options.title) {
                doc.setFontSize(16);
                doc.text(this.options.title, 14, 15);
            }

            // Konfigurasi autoTable
            doc.autoTable({
                startY: this.options.title ? 25 : 15,
                head: [this.getHeaderRow()],
                body: this.getDataRows(),
                styles: {
                    fontSize: 8,
                    cellPadding: 2,
                    overflow: 'linebreak',
                    font: 'helvetica'
                },
                headStyles: {
                    fillColor: [66, 139, 202],
                    textColor: 255,
                    fontSize: 9,
                    fontStyle: 'bold',
                    halign: 'center'
                },
                columnStyles: this.getPDFColumnStyles(),
                didDrawPage: (data) => {
                    // Footer
                    doc.setFontSize(8);
                    doc.text(
                        `Diekspor pada: ${new Date().toLocaleString()}`,
                        data.settings.margin.left,
                        doc.internal.pageSize.height - 10
                    );
                },
                margin: { top: 15, right: 15, bottom: 15, left: 15 },
                theme: 'grid'
            });

            // Simpan file
            doc.save(`${this.options.fileName || 'export'}.pdf`);
            this.triggerExportCallback('success', 'pdf');
        } catch (error) {
            console.error('Error exporting PDF:', error);
            this.triggerExportCallback('error', 'pdf', error);
        }
    }

    exportCSV() {
        try {
            const exportData = this.prepareExportData();
            const headers = this.getHeaderRow().join(',');
            const rows = this.getDataRows().map(row => row.join(','));
            
            const csvContent = [headers, ...rows].join('\n');
            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            
            this.downloadFile(blob, 'csv');
            this.triggerExportCallback('success', 'csv');
        } catch (error) {
            console.error('Error exporting CSV:', error);
            this.triggerExportCallback('error', 'csv', error);
        }
    }

    exportJSON() {
        try {
            const exportData = {
                metadata: {
                    exportDate: new Date(),
                    totalRecords: this.data.data.length,
                    filters: this.activeFilters || {},
                    sorting: this.sortDirection || {}
                },
                data: this.data.data
            };

            const blob = new Blob(
                [JSON.stringify(exportData, null, 2)], 
                { type: 'application/json' }
            );
            
            this.downloadFile(blob, 'json');
            this.triggerExportCallback('success', 'json');
        } catch (error) {
            console.error('Error exporting JSON:', error);
            this.triggerExportCallback('error', 'json', error);
        }
    }

    // Tambahkan metode untuk memuat data secara bertahap
    loadDataInChunks(chunkSize = 1000) {
        const totalData = this.originalData.data.length;
        let loadedData = 0;

        const loadChunk = () => {
            const chunk = this.originalData.data.slice(loadedData, loadedData + chunkSize);
            this.data.data = this.data.data.concat(chunk);
            loadedData += chunk.length;

            this.createTable();

            if (loadedData < totalData) {
                setTimeout(loadChunk, 0);
            }
        };

        loadChunk();
    }

    static createInstance(options) {
        return new TabelMatrix(options);
    }

    setupVirtualScrolling() {
        if (!this.virtualScrolling) return;

        const container = document.getElementById(this.options.containerId);
        container.innerHTML = '';
        
        // Buat wrapper untuk virtual scrolling
        const tableWrapper = document.createElement('div');
        tableWrapper.className = 'virtual-scroll-wrapper';
        tableWrapper.style.height = `${this.options.virtualScrollHeight || 400}px`;
        tableWrapper.style.overflowY = 'auto';
        tableWrapper.style.position = 'relative';
        tableWrapper.style.width = '100%';
        tableWrapper.style.border = '1px solid #ddd';
        tableWrapper.style.backgroundColor = '#fff';
        
        // Buat table dengan struktur fixed
        const table = document.createElement('table');
        table.style.width = '100%';
        table.style.borderCollapse = 'collapse';
        table.style.tableLayout = 'fixed';
        
        // Buat dan tambahkan thead
        const thead = this.createTableHeader();
        thead.style.position = 'sticky';
        thead.style.top = '0';
        thead.style.zIndex = '1';
        thead.style.backgroundColor = '#fff';
        table.appendChild(thead);
        
        // Buat tbody container
        const tbodyContainer = document.createElement('div');
        tbodyContainer.style.position = 'relative';
        tbodyContainer.style.width = '100%';
        // Set tinggi total untuk scrolling
        tbodyContainer.style.height = `${this.data.data.length * this.rowHeight}px`;
        
        // Buat tbody untuk konten yang terlihat
        const tbody = document.createElement('tbody');
        tbody.style.position = 'absolute';
        tbody.style.width = '100%';
        
        table.appendChild(tbody);
        tableWrapper.appendChild(table);
        container.appendChild(tableWrapper);
        
        this.tableWrapper = tableWrapper;
        this.tbody = tbody;
        this.tbodyContainer = tbodyContainer;
        this.table = table;

        // Hitung jumlah baris yang terlihat
        const visibleRowCount = Math.ceil(tableWrapper.clientHeight / this.rowHeight);
        
        // Sesuaikan buffer size
        this.bufferSize = Math.min(
            Math.ceil(visibleRowCount), // Buffer 1x jumlah baris yang terlihat
            50 // Maksimal 50 baris buffer
        );

        let lastScrollTime = 0;
        const scrollThrottleMs = 16;

        tableWrapper.addEventListener('scroll', () => {
            const now = performance.now();
            if (now - lastScrollTime >= scrollThrottleMs) {
                lastScrollTime = now;
                this.handleScroll(tableWrapper);
            }
        });

        // Render awal
        this.handleScroll(tableWrapper);
    }

    handleScroll(wrapper) {
        if (!wrapper || !this.data.data.length) return;
        
        const scrollTop = wrapper.scrollTop;
        const viewportHeight = wrapper.clientHeight;
        const totalHeight = this.data.data.length * this.rowHeight;
        
        // Hitung indeks baris yang terlihat
        const startIndex = Math.floor(scrollTop / this.rowHeight);
        const visibleCount = Math.ceil(viewportHeight / this.rowHeight);
        
        // Hitung range data dengan buffer
        const start = Math.max(0, startIndex - this.bufferSize);
        const end = Math.min(
            this.data.data.length,
            startIndex + visibleCount + this.bufferSize
        );

        // Update scroll container height jika perlu
        this.tbodyContainer.style.height = `${totalHeight}px`;

        // Render hanya jika range berubah
        const currentRange = `${start}-${end}`;
        if (this.lastRange !== currentRange) {
            this.lastRange = currentRange;
            this.renderRows(start, end);
        }
    }

    renderRows(start, end) {
        const fragment = document.createDocumentFragment();
        
        for (let i = start; i < end; i++) {
            const rowData = this.data.data[i];
            if (!rowData) continue;

            const row = document.createElement('tr');
            row.style.position = 'absolute';
            row.style.top = `${i * this.rowHeight}px`;
            row.style.width = '100%';
            row.style.height = `${this.rowHeight}px`;
            row.style.backgroundColor = '#ffffff';
            row.style.display = 'table';
            row.style.tableLayout = 'fixed';
            
            // Tambahkan nomor
            const numberCell = document.createElement('td');
            numberCell.textContent = (i + 1).toString();
            this.applyStyles(numberCell, {
                textAlign: 'center',
                fontSize: '13px',
                fontWeight: '400',
                backgroundColor: '#ffffff',
                padding: '10px',
                border: '1px solid #ccc',
                verticalAlign: 'middle',
                height: `${this.rowHeight}px`
            });
            row.appendChild(numberCell);

            // Render sel data
            this.data.columns.forEach(column => {
                if (column.columns) {
                    column.columns.forEach(subColumn => {
                        const td = this.createDataCell(rowData, subColumn, i);
                        row.appendChild(td);
                    });
                } else {
                    const td = this.createDataCell(rowData, column, i);
                    row.appendChild(td);
                }
            });

            fragment.appendChild(row);
        }

        // Update tbody
        this.tbody.innerHTML = '';
        this.tbody.appendChild(fragment);
    }

    createDataCell(rowData, column, rowIndex) {
        const td = document.createElement('td');
        const fieldToUse = column.field1 || column.field;
        const editConfig = this.editableColumns[fieldToUse];
        
        // Tambahkan class jika ada
        if (column.class) {
            td.className = column.class;
            if (editConfig) {
                td.className += ' editable-cell';
            }
        } else if (editConfig) {
            td.className = 'editable-cell';
        }

        // Buat container untuk konten
        const contentDiv = document.createElement('div');
        contentDiv.style.cssText = 'position: relative; min-height: 20px;';
        
        // Simpan nilai asli
        const originalValue = rowData[column.field];
        
        // Format nilai untuk tampilan
        let formattedValue = '';
        if (editConfig && editConfig[0] === 'search' || editConfig?.type === 'search') {
            // Untuk tipe search, ambil label dari opsi
            const options = Array.isArray(editConfig) ? editConfig[5] : editConfig.options;
            // Cek apakah originalValue adalah object
            const searchValue = typeof originalValue === 'object' ? originalValue.value : originalValue;
            const option = options?.find(opt => opt.value === searchValue);
            formattedValue = option ? option.label : (searchValue || '');
        } else {
            formattedValue = this.formatCellValue(originalValue, column);
        }
        
        contentDiv.innerHTML = formattedValue || '';
        
        // Tambahkan ikon edit jika sel dapat diedit
        if (editConfig) {
            const iconSpan = document.createElement('span');
            iconSpan.className = 'edit-icon';
            iconSpan.innerHTML = '<i data-feather="edit-2" style="width: 14px; height: 14px;"></i>';
            td.appendChild(iconSpan);
            
            if (typeof feather !== 'undefined') {
                feather.replace();
            }
            
            // Event listener untuk edit
            td.addEventListener('click', () => {
                if (this.activeEditor) return;
                
                // Cek kondisional editing jika ada
                if (editConfig.conditional && editConfig.conditional.editable) {
                    if (!editConfig.conditional.editable(rowData)) {
                        return; // Tidak dapat diedit berdasarkan kondisi
                    }
                }
                
                const editor = this.createEditor(
                    editConfig.type || editConfig[0],
                    editConfig,
                    originalValue,
                    (newValue) => {
                        if (newValue !== null) {
                            // Simpan nilai baru
                            rowData[column.field] = newValue;
                            
                            // Format dan tampilkan nilai baru
                            const newFormattedValue = this.formatCellValue(newValue, column);
                            contentDiv.innerHTML = newFormattedValue;
                            
                            if (this.options.onCellEdit) {
                                this.options.onCellEdit(rowData, column.field, newValue, rowIndex);
                            }
                        }
                        editor.remove();
                        contentDiv.style.display = 'block';
                        iconSpan.style.display = 'block';
                    }
                );
                
                contentDiv.style.display = 'none';
                iconSpan.style.display = 'none';
                td.appendChild(editor);
                editor.focus();
                this.activeEditor = editor;
            });
        }
        
        td.appendChild(contentDiv);

        this.applyStyles(td, {
            fontSize: '13px',
            fontWeight: '400',
            backgroundColor: '#ffffff',
            padding: '10px',
            border: '1px solid #ccc',
            textAlign: typeof rowData[column.field] === 'number' ? 'right' : 'left',
            verticalAlign: 'top'
        });

        // Tambahkan CSS classes untuk alignment
        if (td.className.includes('center')) {
            td.style.textAlign = 'center';
        } else if (td.className.includes('right')) {
            td.style.textAlign = 'right';
        } else if (td.className.includes('left')) {
            td.style.textAlign = 'left';
        }

        // Tambahkan CSS class untuk bold
        if (td.className.includes('bold')) {
            td.style.fontWeight = 'bold';
        }

        return td;
    }

    // Tambahkan juga method formatCellValue jika belum ada
    formatCellValue(value, column) {
        if (value === null || value === undefined) return '';
        
        const fieldToUse = column.field1 || column.field;
        
        // Jika ada formatter dan nilai bukan HTML
        if (this.columnFormatters[fieldToUse] && !this.isHTML(value)) {
            return this.columnFormatters[fieldToUse](value);
        }
        
        // Jika nilai sudah dalam format HTML atau tidak ada formatter
        return value;
    }

    // Tambahkan method isHTML jika belum ada
    isHTML(str) {
        if (typeof str !== 'string') return false;
        return str.trim().startsWith('<') && str.trim().endsWith('>');
    }

    // Tambahkan method formatDate ke dalam class TabelMatrix
    formatDate(date, format) {
        const pad = (num) => String(num).padStart(2, '0');
        
        const formatMap = {
            'Y': date.getFullYear(),
            'y': date.getFullYear().toString().slice(-2),
            'm': pad(date.getMonth() + 1),
            'd': pad(date.getDate()),
            'H': pad(date.getHours()),
            'i': pad(date.getMinutes()),
            's': pad(date.getSeconds()),
            'j': date.getDate(),
            'n': date.getMonth() + 1,
            'F': new Intl.DateTimeFormat('id-ID', { month: 'long' }).format(date),
            'M': new Intl.DateTimeFormat('id-ID', { month: 'short' }).format(date),
            'l': new Intl.DateTimeFormat('id-ID', { weekday: 'long' }).format(date),
            'D': new Intl.DateTimeFormat('id-ID', { weekday: 'short' }).format(date),
        };

        return format.split('').map(char => formatMap[char] || char).join('');
    }

    // Tambahkan juga method untuk parsing tanggal
    parseDate(dateString, format) {
        // Jika dateString sudah dalam format ISO atau timestamp
        if (!isNaN(Date.parse(dateString))) {
            return new Date(dateString);
        }

        // Jika format custom
        const formatParts = format.split(/[^YymdHis]/);
        const dateParts = dateString.split(/[^0-9]/);
        const formatMap = {};

        format.split('').forEach((char, index) => {
            if ('YymdHis'.includes(char)) {
                formatMap[char] = dateParts[formatParts.findIndex(part => part.includes(char))];
            }
        });

        const year = formatMap['Y'] || formatMap['y'] || new Date().getFullYear();
        const month = (formatMap['m'] || 1) - 1;
        const day = formatMap['d'] || 1;
        const hours = formatMap['H'] || 0;
        const minutes = formatMap['i'] || 0;
        const seconds = formatMap['s'] || 0;

        return new Date(year, month, day, hours, minutes, seconds);
    }

    // Tambahkan method Reload
    Reload(options) {
        // Validasi parameter
        if (!options || (!options.row && !options.data)) {
            console.warn('Parameter reload tidak valid. Gunakan format: { row: dataset } atau { data: { columns: [], data: [] }}');
            return;
        }

        try {
            // Update data berdasarkan parameter yang diberikan
            if (options.row) {
                // Jika hanya update data rows
                this.data.data = [...options.row];
                this.originalData.data = [...options.row];
            } else if (options.data) {
                // Jika update keseluruhan struktur data
                this.data = {
                    columns: [...(options.data.columns || this.data.columns)],
                    data: [...options.data.data]
                };
                this.originalData = {
                    columns: [...(options.data.columns || this.data.columns)],
                    data: [...options.data.data]
                };
            }

            // Reset state
            this.currentPage = 1;
            this.sortDirection = {};
            
            // Rebuild search index
            this.buildSearchIndex();
            
            // Reset filters jika ada
            if (this.filters) {
                Object.keys(this.filters).forEach(filterKey => {
                    const filterConfig = this.filters[filterKey];
                    const filterElement = document.getElementById(filterConfig.element);
                    if (filterElement) {
                        if (filterConfig.type === 'select') {
                            this.populateFilterOptions(filterElement, filterConfig.field);
                        }
                        filterElement.value = 'all';
                    }
                });
            }

            // Perbarui tampilan
            if (this.virtualScrolling) {
                this.setupVirtualScrolling();
            } else {
                this.createTable();
                this.createPagination();
            }

            //console.log('Tabel berhasil dimuat ulang');
            return true;

        } catch (error) {
            console.error('Gagal memuat ulang tabel:', error);
            return false;
        }
    }

    // Tambahkan method addTabel
    addTabel(newData) {
        try {
            // Validasi parameter
            if (!newData || !newData.columns) {
                throw new Error('Data tidak valid. Format yang benar: { columns: [...] }');
            }

            // Buat data row baru dari values di kolom
            const newRow = {};
            
            // Map nilai-nilai dari kolom baru ke struktur kolom yang ada
            newData.columns.forEach(column => {
                if (column.columns) {
                    column.columns.forEach(subCol => {
                        // Gunakan field dari struktur kolom yang ada jika ada
                        const existingSubCol = this.originalData.columns
                            .find(c => c.columns)?.columns
                            .find(sc => sc.title === subCol.title);
                        
                        const field = existingSubCol?.field || subCol.field || `field_${Math.random().toString(36).substr(2, 9)}`;
                        if (subCol.value !== undefined) {
                            newRow[field] = subCol.value;
                        }
                    });
                } else {
                    // Untuk kolom tunggal
                    const existingCol = this.originalData.columns
                        .find(c => c.title === column.title);
                    
                    const field = existingCol?.field || column.field || column.value;
                    if (column.value !== undefined) {
                        newRow[field] = column.value;
                    }
                }
            });

            // Gabungkan data baru dengan data yang ada
            this.data = {
                // Gunakan struktur kolom yang ada
                columns: [...this.originalData.columns],
                // Tambahkan data baru di awal array
                data: [newRow, ...this.originalData.data]
            };

            // Update originalData juga
            this.originalData = {
                columns: [...this.originalData.columns],
                data: [newRow, ...this.originalData.data]
            };

            // Reset state
            this.currentPage = 1;
            this.sortDirection = {};
            
            // Rebuild search index
            this.buildSearchIndex();

            // Perbarui tampilan
            if (this.virtualScrolling) {
                this.setupVirtualScrolling();
            } else {
                this.createTable();
                this.createPagination();
            }

            //console.log('Data baru berhasil ditambahkan di urutan pertama');
            return true;

        } catch (error) {
            console.error('Gagal menambahkan data:', error);
            return false;
        }
    }

    // Tambahkan method filterKey ke dalam class TabelMatrix
    filterKey(key, value) {
        try {
            // Validasi parameter
            if (!key || value === undefined) {
                throw new Error('Parameter tidak valid. Gunakan format: filterKey(key, value)');
            }

            // Simpan data yang difilter
            const filteredData = this.originalData.data.filter(row => {
                // Konversi nilai ke string untuk perbandingan yang konsisten
                const rowValue = String(row[key] || '').toLowerCase();
                const searchValue = String(value).toLowerCase();
                
                // Gunakan includes untuk pencocokan parsial
                return rowValue.includes(searchValue);
            });

            // Update data tabel dengan hasil filter
            this.data = {
                columns: [...this.originalData.columns],
                data: filteredData
            };

            // Reset state
            this.currentPage = 1;
            this.sortDirection = {};
            
            // Rebuild search index
            this.buildSearchIndex();

            // Perbarui tampilan
            if (this.virtualScrolling) {
                this.setupVirtualScrolling();
            } else {
                this.createTable();
                this.createPagination();
            }

            //console.log(`Data berhasil difilter berdasarkan ${key}=${value}`);
            return true;

        } catch (error) {
            console.error('Gagal memfilter data:', error);
            return false;
        }
    }

    // Tambahkan method untuk mereset filter
    resetFilter() {
        // Kembalikan ke data original
        this.data = {
            columns: [...this.originalData.columns],
            data: [...this.originalData.data]
        };

        // Reset state
        this.currentPage = 1;
        this.sortDirection = {};
        
        // Rebuild search index
        this.buildSearchIndex();

        // Perbarui tampilan
        if (this.virtualScrolling) {
            this.setupVirtualScrolling();
        } else {
            this.createTable();
            this.createPagination();
        }

        //console.log('Filter direset');
    }

    // Method baru untuk menangani pembuatan editor
    createEditor(type, config, value, callback) {
        if (type === 'search') {
            const editor = document.createElement('select');
            
            // Handle konfigurasi array format
            let width, placeholder, searchable, options;
            
            if (Array.isArray(config)) {
                [, width, , placeholder, searchable, options] = config;
            } else {
                width = config.width || 6;
                placeholder = config.placeholder;
                searchable = config.searchable;
                options = config.options;
            }
            
            editor.className = `form-control col-md-${width}`;
            editor.innerHTML = `<option value="">${placeholder || 'Pilih...'}</option>`;
            
            // Handle options
            const optionsList = Array.isArray(options) ? options : 
                (typeof options === 'function' ? options(this.currentRow) : []);
            
            // Tambahkan semua opsi ke select dan cari label yang sesuai dengan value
            let selectedLabel = '';
            optionsList.forEach(opt => {
                const option = document.createElement('option');
                option.value = opt.value;
                option.textContent = opt.label;
                
                // Simpan label jika value cocok
                if (value === opt.value) {
                    option.selected = true;
                    selectedLabel = opt.label;
                }
                
                editor.appendChild(option);
            });

            // Tambahkan fitur searchable
            if (searchable) {
                if (window.jQuery && window.jQuery.fn.select2) {
                    jQuery(editor).select2({
                        placeholder: placeholder || 'Pilih...',
                        allowClear: true,
                        width: '100%',
                        dropdownParent: jQuery(editor).parent(),
                        // Inisialisasi dengan label yang sesuai
                        initSelection: function(element, callback) {
                            if (value && selectedLabel) {
                                callback({ id: value, text: selectedLabel });
                            }
                        }
                    });

                    // Set nilai awal untuk select2
                    if (value && selectedLabel) {
                        const newOption = new Option(selectedLabel, value, true, true);
                        jQuery(editor).append(newOption).trigger('change');
                    }
                }
            }

            // Event handlers
            editor.addEventListener('change', () => {
                const newValue = editor.value;
                const selectedOption = optionsList.find(opt => opt.value === newValue);
                if (newValue && selectedOption) {
                    // Hanya simpan value, bukan object
                    callback(newValue);
                } else {
                    callback(null);
                }
                this.activeEditor = null;
            });

            editor.addEventListener('blur', () => {
                setTimeout(() => {
                    if (!this.activeEditor) return;
                    const newValue = editor.value;
                    const selectedOption = optionsList.find(opt => opt.value === newValue);
                    if (newValue && selectedOption) {
                        // Hanya simpan value, bukan object
                        callback(newValue);
                    } else {
                        callback(null);
                    }
                    this.activeEditor = null;
                }, 100);
            });

            editor.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') {
                    e.preventDefault();
                    this.activeEditor = null;
                    callback(null);
                }
            });

            return editor;
        }
        
        // Lanjutkan dengan kode editor yang sudah ada
        const cleanValue = this.cleanValueForEditor(value);
        
        const editor = document.createElement(type === 'select' ? 'select' : 'input');
        
        // Dapatkan konfigurasi yang diperluas
        const editorConfig = this.getEditorConfig(type, config, cleanValue);
        
        switch(type) {
            case 'text':
            case 'number':
                editor.type = type;
                editor.className = `form-control col-md-${editorConfig.width}`;
                editor.placeholder = editorConfig.placeholder;
                editor.value = cleanValue;
                
                // Tambahkan validasi HTML5
                if (editorConfig.validation) {
                    if (editorConfig.validation.required) editor.required = true;
                    if (editorConfig.validation.minLength) editor.minLength = editorConfig.validation.minLength;
                    if (editorConfig.validation.maxLength) editor.maxLength = editorConfig.validation.maxLength;
                    if (editorConfig.validation.pattern) editor.pattern = editorConfig.validation.pattern;
                    if (type === 'number') {
                        if (editorConfig.validation.min) editor.min = editorConfig.validation.min;
                        if (editorConfig.validation.max) editor.max = editorConfig.validation.max;
                        if (editorConfig.validation.step) editor.step = editorConfig.validation.step;
                    }
                }
                break;
                
            case 'select':
                editor.className = `form-control col-md-${editorConfig.width}`;
                editor.innerHTML = `<option value="">${editorConfig.placeholder}</option>`;
                
                // Handle dynamic options
                const options = typeof editorConfig.options === 'function' 
                    ? editorConfig.options(this.currentRow)
                    : editorConfig.options;
                    
                options.forEach(opt => {
                    const option = document.createElement('option');
                    option.value = opt.value;
                    option.textContent = opt.label;
                    option.selected = cleanValue === opt.value;
                    editor.appendChild(option);
                });
                
                // Add searchable feature if enabled
                if (editorConfig.searchable) {
                    this.makeSelectSearchable(editor);
                }
                break;
                
            case 'datepicker':
                editor.type = 'date';
                editor.className = `form-control col-md-${editorConfig.width}`;
                editor.placeholder = editorConfig.placeholder;
                
                // Handle date constraints
                if (editorConfig.minDate) editor.min = editorConfig.minDate;
                if (editorConfig.maxDate) editor.max = editorConfig.maxDate;
                
                // Format tanggal untuk input
                if (cleanValue) {
                    const date = new Date(cleanValue);
                    editor.value = date.toISOString().split('T')[0];
                }

                // Tambahkan event handler khusus untuk datepicker
                editor.addEventListener('change', () => {
                    if (!editor.value) {
                        callback(null);
                        return;
                    }

                    const date = new Date(editor.value);
                    let formattedDate;

                    // Format sesuai dengan placeholder yang ditentukan
                    const format = editorConfig.placeholder;
                    if (format) {
                        formattedDate = this.formatDate(date, format);
                    } else {
                        formattedDate = date.toISOString().split('T')[0];
                    }

                    callback(formattedDate);
                    this.activeEditor = null;
                });

                break;
                
            case 'textarea':
                const textarea = document.createElement('textarea');
                textarea.className = `form-control col-md-${editorConfig.width}`;
                textarea.placeholder = editorConfig.placeholder;
                textarea.value = cleanValue;
                textarea.rows = editorConfig.rows || 3;
                if (editorConfig.maxLength) textarea.maxLength = editorConfig.maxLength;
                return textarea;
                
            case 'checkbox':
            case 'radio':
                const wrapper = document.createElement('div');
                wrapper.className = `col-md-${editorConfig.width}`;
                
                editorConfig.options.forEach(opt => {
                    const container = document.createElement('div');
                    container.className = `form-check`;
                    
                    const input = document.createElement('input');
                    input.type = type;
                    input.className = 'form-check-input';
                    input.name = editorConfig.name || 'group';
                    input.value = opt.value;
                    input.checked = cleanValue === opt.value;
                    
                    const label = document.createElement('label');
                    label.className = 'form-check-label';
                    label.textContent = opt.label;
                    
                    container.appendChild(input);
                    container.appendChild(label);
                    wrapper.appendChild(container);
                });
                return wrapper;
        }

        // Tambahkan event handlers
        if (editorConfig.events) {
            Object.entries(editorConfig.events).forEach(([event, handler]) => {
                editor.addEventListener(event, handler);
            });
        }

        // Event handlers default
        editor.addEventListener('blur', () => {
            setTimeout(() => {
                if (!this.activeEditor) return;
                
                let newValue = editor.value;
                
                // Validasi
                if (editorConfig.validation && !this.validateValue(newValue, editorConfig.validation)) {
                    editor.classList.add('is-invalid');
                    return;
                }
                
                // Transform nilai setelah edit
                if (editorConfig.transform && editorConfig.transform.afterEdit) {
                    newValue = editorConfig.transform.afterEdit(newValue);
                }
                
                this.activeEditor = null;
                callback(newValue);
            }, 100);
        });
        
        editor.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && type !== 'textarea') {
                e.preventDefault();
                let newValue = editor.value;
                
                if (editorConfig.validation && !this.validateValue(newValue, editorConfig.validation)) {
                    editor.classList.add('is-invalid');
                    return;
                }
                
                if (editorConfig.transform && editorConfig.transform.afterEdit) {
                    newValue = editorConfig.transform.afterEdit(newValue);
                }
                
                this.activeEditor = null;
                callback(newValue);
            }
            if (e.key === 'Escape') {
                e.preventDefault();
                this.activeEditor = null;
                callback(null);
            }
        });

        return editor;
    }

    // Method baru untuk mendapatkan konfigurasi editor yang diperluas
    getEditorConfig(type, config, value) {
        // Jika config adalah array (format lama)
        if (Array.isArray(config)) {
            return {
                type: type,
                width: config[1],
                label: config[2],
                placeholder: config[3],
                options: config[4] || []
            };
        }
        
        // Format baru (object)
        return {
            type: type,
            width: config.width || 12,
            label: config.label || '',
            placeholder: config.placeholder || '',
            validation: config.validation || {},
            transform: config.transform || {},
            events: config.events || {},
            options: config.options || [],
            searchable: config.searchable || false,
            multiple: config.multiple || false,
            rows: config.rows,
            maxLength: config.maxLength,
            minDate: config.minDate,
            maxDate: config.maxDate,
            format: config.format,
            conditional: config.conditional || {}
        };
    }

    // Method untuk validasi nilai
    validateValue(value, validation) {
        if (!validation) return true;
        
        if (validation.required && !value) return false;
        if (validation.minLength && value.length < validation.minLength) return false;
        if (validation.maxLength && value.length > validation.maxLength) return false;
        if (validation.pattern && !validation.pattern.test(value)) return false;
        if (validation.custom && !validation.custom(value)) return false;
        
        return true;
    }

    // Method untuk membuat select searchable
    makeSelectSearchable(select) {
        // Implementasi fitur pencarian untuk select
        // Bisa menggunakan library seperti select2 atau implementasi custom
    }

    // Method untuk membersihkan HTML untuk editor
    cleanValueForEditor(value) {
        if (!value) return '';
        
        // Jika nilai bukan string, konversi ke string
        if (typeof value !== 'string') {
            return String(value);
        }
        
        // Jika nilai adalah HTML
        if (this.isHTML(value)) {
            const temp = document.createElement('div');
            temp.innerHTML = value;
            const cleanText = temp.textContent || temp.innerText || '';
            temp.remove();
            return cleanText;
        }
        
        // Jika nilai bukan HTML, kembalikan apa adanya
        return value;
    }

    // Tambahkan method helper untuk mendapatkan label dari nilai search
    getSearchLabel(value, config) {
        if (!value) return '';
        
        const options = config.options || config[4] || [];
        const option = options.find(opt => opt.value === value);
        return option ? option.label : value;
    }

    // Tambahkan method setupFilters
    setupFilters() {
        if (!this.filters) return;
        
        // Iterasi semua filter yang didefinisikan
        Object.keys(this.filters).forEach(filterKey => {
            const filterConfig = this.filters[filterKey];
            const filterElement = document.getElementById(filterConfig.element);
            
            if (filterElement) {
                // Jika tipe select, populate options
                if (filterConfig.type === 'select') {
                    this.populateFilterOptions(filterElement, filterConfig.field);
                }
                
                // Tambahkan event listener
                filterElement.addEventListener('change', () => {
                    this.filterData();
                });
            }
        });
    }

    // Tambahkan method populateFilterOptions
    populateFilterOptions(selectElement, field) {
        // Dapatkan nilai unik untuk field
        const uniqueValues = [...new Set(this.originalData.data
            .map(item => item[field])
            .filter(Boolean))] // Remove null/undefined
            .sort();
        
        // Tambahkan options ke select element
        selectElement.innerHTML = `
            <option value="all">Semua</option>
            ${uniqueValues.map(value => 
                `<option value="${value}">${value}</option>`
            ).join('')}
        `;
    }

    // Tambahkan method filterData
    filterData() {
        // Mulai dengan semua data original
        let filteredData = [...this.originalData.data];

        // Terapkan setiap filter secara berurutan
        Object.keys(this.filters).forEach(filterKey => {
            const filterConfig = this.filters[filterKey];
            const filterElement = document.getElementById(filterConfig.element);
            
            if (filterElement && filterElement.value && filterElement.value !== 'all') {
                filteredData = filteredData.filter(row => {
                    const filterField = filterConfig.field;
                    const rowValue = row[filterField];
                    
                    // Handle different filter types
                    switch(filterConfig.type) {
                        case 'select':
                            return String(rowValue) === String(filterElement.value);
                        case 'date':
                            if (!rowValue || !filterElement.value) return false;
                            const rowDate = new Date(rowValue);
                            const filterDate = new Date(filterElement.value);
                            return rowDate.toDateString() === filterDate.toDateString();
                        default:
                            return String(rowValue) === String(filterElement.value);
                    }
                });
            }
        });

        // Update data tabel dengan hasil filter
        this.data = {
            columns: [...this.originalData.columns],
            data: filteredData
        };

        // Reset state dan perbarui tampilan
        this.currentPage = 1;
        this.createTable();
        this.createPagination();
        this.buildSearchIndex();
    }
}
//AND Tabel Matrix