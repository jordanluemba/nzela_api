# 📸 **Guide d'Affichage des Images - Frontend NZELA**

## 🎯 **URLs pour Afficher les Images**

### **Endpoint Principal**
```
GET /api/image.php?path=uploads/signalements/photo_123456_7890.jpg
```

### **Structure des Réponses API**

#### **1. Signalements avec Photos**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "code": "NZELA-20251004-1234",
      "description": "Nid-de-poule sur la route",
      "photo_principale": "uploads/signalements/photo_1696401234_5678.jpg",
      "photo_url": "http://localhost/api/image.php?path=uploads%2Fsignalements%2Fphoto_1696401234_5678.jpg",
      "type_nom": "Infrastructure",
      "type_image": "uploads/types/type_1696401000_1234.png",
      "type_image_url": "http://localhost/api/image.php?path=uploads%2Ftypes%2Ftype_1696401000_1234.png"
    }
  ]
}
```

#### **2. Types avec Images**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "nom": "Infrastructure",
      "description": "Problèmes de routes, ponts, etc.",
      "image_path": "uploads/types/type_1696401000_1234.png",
      "image_url": "http://localhost/api/image.php?path=uploads%2Ftypes%2Ftype_1696401000_1234.png"
    }
  ]
}
```

---

## 💻 **Code Frontend pour Afficher les Images**

### **JavaScript/React Example**
```javascript
// Afficher une photo de signalement
function displaySignalementPhoto(signalement) {
    const img = document.createElement('img');
    
    if (signalement.photo_url) {
        img.src = signalement.photo_url;
        img.alt = `Photo du signalement ${signalement.code}`;
        img.className = 'signalement-photo';
        
        // Gestion d'erreur si l'image ne charge pas
        img.onerror = function() {
            this.src = '/assets/images/no-photo-placeholder.jpg';
        };
    } else {
        img.src = '/assets/images/no-photo-placeholder.jpg';
        img.alt = 'Aucune photo disponible';
    }
    
    return img;
}

// Afficher l'icône d'un type
function displayTypeIcon(type) {
    const img = document.createElement('img');
    
    if (type.image_url) {
        img.src = type.image_url;
        img.alt = `Icône ${type.nom}`;
        img.className = 'type-icon';
    } else {
        // Icône par défaut
        img.src = '/assets/icons/default-type.svg';
        img.alt = type.nom;
    }
    
    return img;
}
```

### **HTML Example**
```html
<!-- Photo de signalement -->
<div class="signalement-card">
    <h3>Signalement NZELA-20251004-1234</h3>
    <img src="http://localhost/api/image.php?path=uploads%2Fsignalements%2Fphoto_1696401234_5678.jpg" 
         alt="Photo du signalement" 
         class="signalement-photo"
         onerror="this.src='/assets/images/no-photo.jpg'">
    <p>Description du problème...</p>
</div>

<!-- Icône de type -->
<div class="type-selector">
    <img src="http://localhost/api/image.php?path=uploads%2Ftypes%2Ftype_1696401000_1234.png" 
         alt="Infrastructure" 
         class="type-icon">
    <span>Infrastructure</span>
</div>
```

### **CSS pour le Style**
```css
/* Photos de signalements */
.signalement-photo {
    width: 100%;
    max-width: 400px;
    height: 200px;
    object-fit: cover;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

/* Icônes de types */
.type-icon {
    width: 48px;
    height: 48px;
    object-fit: contain;
    border-radius: 8px;
}

/* Responsive */
@media (max-width: 768px) {
    .signalement-photo {
        height: 150px;
    }
    
    .type-icon {
        width: 32px;
        height: 32px;
    }
}
```

---

## 🔄 **Exemples d'Utilisation Pratiques**

### **1. Fetching et Affichage de Signalements**
```javascript
async function loadSignalements() {
    try {
        const response = await fetch('/api/signalements/list.php');
        const data = await response.json();
        
        if (data.success) {
            data.data.forEach(signalement => {
                // Créer le HTML pour chaque signalement
                const signalementDiv = document.createElement('div');
                signalementDiv.innerHTML = `
                    <h3>${signalement.code}</h3>
                    <img src="${signalement.photo_url || '/assets/images/no-photo.jpg'}" 
                         alt="Photo du signalement" 
                         class="signalement-photo">
                    <p>${signalement.description}</p>
                    <div class="type-info">
                        <img src="${signalement.type_image_url || '/assets/icons/default.svg'}" 
                             alt="${signalement.type_nom}" 
                             class="type-icon">
                        <span>${signalement.type_nom}</span>
                    </div>
                `;
                
                document.getElementById('signalements-container').appendChild(signalementDiv);
            });
        }
    } catch (error) {
        console.error('Erreur lors du chargement des signalements:', error);
    }
}
```

### **2. Sélecteur de Types avec Images**
```javascript
async function loadTypeSelector() {
    try {
        const response = await fetch('/api/types/list.php');
        const data = await response.json();
        
        if (data.success) {
            const selector = document.getElementById('type-selector');
            
            data.data.forEach(type => {
                const typeButton = document.createElement('button');
                typeButton.className = 'type-button';
                typeButton.dataset.typeId = type.id;
                typeButton.innerHTML = `
                    <img src="${type.image_url || '/assets/icons/default.svg'}" 
                         alt="${type.nom}" 
                         class="type-icon">
                    <span>${type.nom}</span>
                `;
                
                typeButton.addEventListener('click', () => {
                    selectType(type.id);
                });
                
                selector.appendChild(typeButton);
            });
        }
    } catch (error) {
        console.error('Erreur lors du chargement des types:', error);
    }
}
```

---

## 🛡️ **Sécurité et Performance**

### **Protection**
- ✅ Validation stricte des chemins d'images
- ✅ Seuls les fichiers dans `uploads/` sont accessibles
- ✅ Types MIME vérifiés
- ✅ Headers de sécurité automatiques

### **Performance**
- ✅ Cache navigateur 1 an
- ✅ Optimisation automatique des images
- ✅ Compression des images à l'upload

### **Gestion d'Erreurs**
```javascript
// Gestion d'erreur pour images manquantes
function handleImageError(img) {
    img.onerror = function() {
        this.src = '/assets/images/placeholder.jpg';
        this.alt = 'Image non disponible';
    };
}
```

---

## 📱 **Responsive et Mobile**

### **Tailles Recommandées**
- **Photos signalements** : 400x200px (desktop), 300x150px (mobile)
- **Icônes types** : 48x48px (desktop), 32x32px (mobile)
- **Thumbnails** : 120x120px

### **Lazy Loading**
```javascript
// Lazy loading pour les performances
function enableLazyLoading() {
    const images = document.querySelectorAll('img[data-src]');
    const imageObserver = new IntersectionObserver((entries, observer) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const img = entry.target;
                img.src = img.dataset.src;
                img.classList.remove('lazy');
                imageObserver.unobserve(img);
            }
        });
    });

    images.forEach(img => imageObserver.observe(img));
}
```

---

## 🎯 **Résumé pour le Frontend**

### **URLs à Utiliser :**
1. **Photos de signalements** : `signalement.photo_url`
2. **Icônes de types** : `type.image_url`
3. **Endpoint direct** : `/api/image.php?path=...`

### **Champs dans les Réponses API :**
- `photo_url` - URL complète pour afficher la photo
- `image_url` - URL complète pour afficher l'icône du type
- `photo_principale` - Chemin relatif stocké en BDD
- `image_path` - Chemin relatif de l'icône du type

Votre frontend peut maintenant afficher toutes les images de manière sécurisée et optimisée ! 🚀