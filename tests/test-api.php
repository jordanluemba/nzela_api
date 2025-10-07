<?php
/**
 * NZELA API - Script de Tests Automatisés
 * Exécute tous les tests et génère un rapport
 */

class APITester {
    private $baseUrl;
    private $results = [];
    private $cookies = [];
    
    public function __construct($baseUrl = 'http://localhost/api') {
        $this->baseUrl = rtrim($baseUrl, '/');
    }
    
    /**
     * Exécuter tous les tests
     */
    public function runAllTests() {
        echo "🧪 DÉBUT DES TESTS API NZELA\n";
        echo "================================\n\n";
        
        $this->testTypes();
        $this->testAuth();
        $this->testSignalements();
        $this->testErrorHandling();
        
        $this->displayResults();
    }
    
    /**
     * Test des types de signalements
     */
    private function testTypes() {
        echo "📋 Tests Types de Signalements\n";
        echo "------------------------------\n";
        
        // Test liste des types
        $response = $this->makeRequest('GET', '/types/list.php');
        $this->assertTest('Liste des types', $response['success'] ?? false, $response);
        
        if ($response['success'] && !empty($response['data'])) {
            $firstType = $response['data'][0];
            $typeId = $firstType['id'];
            
            // Test détail d'un type
            $response = $this->makeRequest('GET', "/types/detail.php?id={$typeId}");
            $this->assertTest('Détail type ID=' . $typeId, $response['success'] ?? false, $response);
        }
        
        echo "\n";
    }
    
    /**
     * Test authentification
     */
    private function testAuth() {
        echo "🔐 Tests Authentification\n";
        echo "-------------------------\n";
        
        $testEmail = 'test-' . time() . '@nzela.com';
        $testPassword = 'motdepasse123';
        
        // Test inscription
        $registerData = [
            'firstName' => 'Test',
            'lastName' => 'User',
            'email' => $testEmail,
            'password' => $testPassword,
            'province' => 'Kinshasa'
        ];
        
        $response = $this->makeRequest('POST', '/auth/register.php', $registerData);
        $this->assertTest('Inscription utilisateur', $response['success'] ?? false, $response);
        
        // Test connexion
        $loginData = [
            'email' => $testEmail,
            'password' => $testPassword
        ];
        
        $response = $this->makeRequest('POST', '/auth/login.php', $loginData);
        $this->assertTest('Connexion utilisateur', $response['success'] ?? false, $response);
        
        // Test profil (connecté)
        $response = $this->makeRequest('GET', '/auth/me.php');
        $this->assertTest('Profil utilisateur', $response['success'] ?? false, $response);
        
        // Test mauvais mot de passe
        $badLoginData = [
            'email' => $testEmail,
            'password' => 'mauvaismdp'
        ];
        
        $response = $this->makeRequest('POST', '/auth/login.php', $badLoginData);
        $this->assertTest('Connexion échec (attendu)', !($response['success'] ?? true), $response);
        
        echo "\n";
    }
    
    /**
     * Test signalements
     */
    private function testSignalements() {
        echo "📝 Tests Signalements\n";
        echo "--------------------\n";
        
        // Test création signalement
        $signalementData = [
            'type_signalement_id' => 1,
            'province' => 'Kinshasa',
            'ville' => 'Kinshasa',
            'commune' => 'Gombe',
            'description' => 'Test automatisé - ' . date('Y-m-d H:i:s'),
            'urgence' => 'Moyen'
        ];
        
        $response = $this->makeRequest('POST', '/signalements/create.php', $signalementData);
        $this->assertTest('Création signalement', $response['success'] ?? false, $response);
        
        $signalementCode = $response['data']['code'] ?? null;
        
        // Test liste signalements
        $response = $this->makeRequest('GET', '/signalements/list.php');
        $this->assertTest('Liste signalements', $response['success'] ?? false, $response);
        
        // Test détail signalement
        if ($signalementCode) {
            $response = $this->makeRequest('GET', "/signalements/detail.php?code={$signalementCode}");
            $this->assertTest('Détail signalement', $response['success'] ?? false, $response);
        }
        
        // Test mes signalements
        $response = $this->makeRequest('GET', '/signalements/user.php');
        $this->assertTest('Mes signalements', $response['success'] ?? false, $response);
        
        echo "\n";
    }
    
    /**
     * Test gestion d'erreurs
     */
    private function testErrorHandling() {
        echo "⚠️ Tests Gestion d'Erreurs\n";
        echo "--------------------------\n";
        
        // Test données manquantes
        $response = $this->makeRequest('POST', '/auth/register.php', ['email' => 'test@test.com']);
        $this->assertTest('Données manquantes (attendu)', !($response['success'] ?? true), $response);
        
        // Test méthode non autorisée
        $response = $this->makeRequest('GET', '/auth/register.php');
        $this->assertTest('Méthode non autorisée (attendu)', !($response['success'] ?? true), $response);
        
        // Test endpoint inexistant
        $response = $this->makeRequest('GET', '/inexistant.php');
        $this->assertTest('Endpoint inexistant (attendu)', false, $response);
        
        echo "\n";
    }
    
    /**
     * Faire une requête HTTP
     */
    private function makeRequest($method, $endpoint, $data = null) {
        $url = $this->baseUrl . $endpoint;
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_COOKIEJAR => '',
            CURLOPT_COOKIEFILE => ''
        ]);
        
        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            return ['error' => $error, 'http_code' => $httpCode];
        }
        
        $decoded = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return ['error' => 'Invalid JSON response', 'raw' => $response, 'http_code' => $httpCode];
        }
        
        $decoded['http_code'] = $httpCode;
        return $decoded;
    }
    
    /**
     * Vérifier et enregistrer un test
     */
    private function assertTest($testName, $condition, $response) {
        $status = $condition ? '✅' : '❌';
        $httpCode = $response['http_code'] ?? 'N/A';
        
        echo sprintf("%-30s %s (HTTP %s)\n", $testName, $status, $httpCode);
        
        if (!$condition && isset($response['error'])) {
            echo "   └─ Erreur: " . $response['error'] . "\n";
        }
        
        $this->results[] = [
            'test' => $testName,
            'success' => $condition,
            'response' => $response
        ];
    }
    
    /**
     * Afficher le résumé des résultats
     */
    private function displayResults() {
        $total = count($this->results);
        $passed = array_filter($this->results, fn($r) => $r['success']);
        $passedCount = count($passed);
        $failedCount = $total - $passedCount;
        
        echo "📊 RÉSULTATS FINAUX\n";
        echo "==================\n";
        echo sprintf("Total: %d tests\n", $total);
        echo sprintf("✅ Réussis: %d (%.1f%%)\n", $passedCount, ($passedCount / $total) * 100);
        echo sprintf("❌ Échoués: %d (%.1f%%)\n", $failedCount, ($failedCount / $total) * 100);
        
        if ($failedCount > 0) {
            echo "\n⚠️ Tests échoués:\n";
            foreach ($this->results as $result) {
                if (!$result['success']) {
                    echo "- " . $result['test'] . "\n";
                }
            }
        }
        
        echo "\n🎉 Tests terminés !\n";
    }
}

// Exécution des tests
if (php_sapi_name() === 'cli') {
    $tester = new APITester();
    $tester->runAllTests();
} else {
    echo "Ce script doit être exécuté en ligne de commande.\n";
    echo "Usage: php test-api.php\n";
}
?>