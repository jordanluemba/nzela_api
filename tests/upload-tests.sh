# Tests pour l'Upload de Photos
# Ces tests nécessitent des fichiers réels

###############################################
# UPLOAD AVEC CURL
###############################################

# Test upload photo valide (JPG)
curl -X POST \
  http://localhost/api/upload/photo.php \
  -H "Content-Type: multipart/form-data" \
  -F "photo=@./test-images/photo-test.jpg"

# Test upload photo PNG
curl -X POST \
  http://localhost/api/upload/photo.php \
  -H "Content-Type: multipart/form-data" \
  -F "photo=@./test-images/photo-test.png"

# Test upload photo WebP
curl -X POST \
  http://localhost/api/upload/photo.php \
  -H "Content-Type: multipart/form-data" \
  -F "photo=@./test-images/photo-test.webp"

###############################################
# TESTS D'ERREURS UPLOAD
###############################################

# Test fichier trop volumineux (> 5MB)
curl -X POST \
  http://localhost/api/upload/photo.php \
  -H "Content-Type: multipart/form-data" \
  -F "photo=@./test-images/large-file.jpg"

# Test type de fichier non autorisé (PDF)
curl -X POST \
  http://localhost/api/upload/photo.php \
  -H "Content-Type: multipart/form-data" \
  -F "photo=@./test-files/document.pdf"

# Test sans fichier
curl -X POST \
  http://localhost/api/upload/photo.php \
  -H "Content-Type: multipart/form-data"