<?php
require_once __DIR__ . '/../../bootstrap.php';
// Session is already started by bootstrap.php via SessionManager::start()

$errors = [];

try {
    // Test database connection
    try {
        $testStmt = db()->prepare("SELECT 1");
        $testStmt->execute();
    } catch (Throwable $e) {
        error_log('Database connection test failed: ' . $e->getMessage());
        throw new Exception('Database connection failed: ' . $e->getMessage());
    }

    // Check for AJAX
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';

        if ($action === 'login') {
            try {
                // Pass true if AJAX to prevent auto-redirect
                $result = AuthController::login($isAjax);

                // If it returned errors (indexed array of strings)
                if (isset($result[0]) && is_string($result[0])) {
                    $errors = $result;
                } elseif (isset($result['_redirect'])) {
                    // Success or 2FA required
                    if ($isAjax) {
                        while (ob_get_level()) ob_end_clean();
                        header('Content-Type: application/json');
                        $response = ['success' => true, 'redirect' => $result['_redirect']];
                        if (!empty($result['_2fa_required'])) {
                            $response['require_2fa'] = true;
                        }
                        echo json_encode($response);
                        exit;
                    }
                    // Should rely on AuthController's redirect if not ajax, 
                    // but we passed $isAjax=false so it should have redirected already.
                    // If we are here and not ajax, something odd happened.
                }
            } catch (Throwable $e) {
                error_log('AuthController::login() error: ' . $e->getMessage());
                $errors[] = 'Login failed: ' . $e->getMessage();
            }

            if (!empty($errors)) {
                if ($isAjax) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'errors' => $errors]);
                    exit;
                }
                $_SESSION['flash_login_errors'] = $errors;
                redirect('/');
            }
        } elseif ($action === 'register') {
            try {
                // RegisterController usually redirects on success too. 
                // Pass true for preventRedirect if AJAX
                $result = RegisterController::registerPatient($isAjax);

                // Check if result is success array or errors
                if (isset($result[0]) && is_string($result[0])) {
                    $errors = $result;
                } elseif (isset($result['_success'])) {
                    if ($isAjax) {
                        while (ob_get_level()) ob_end_clean();
                        header('Content-Type: application/json');
                        echo json_encode(['success' => true, 'redirect' => $result['_redirect']]);
                        exit;
                    }
                    // Should have redirected if strictly following logic, but if not:
                    redirect($result['_redirect']);
                }
            } catch (Throwable $e) {
                error_log('RegisterController error: ' . $e->getMessage());
                $errors[] = 'Registration failed: ' . $e->getMessage();
            }

            if (!empty($errors)) {
                if ($isAjax) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'errors' => $errors]);
                    exit;
                }
                $_SESSION['flash_register_errors'] = $errors;
                redirect('/');
            } else {
                // If success reached here (fallback)
                if ($isAjax) {
                    while (ob_get_level()) ob_end_clean();
                    header('Content-Type: application/json');
                    echo json_encode(['success' => true, 'redirect' => base_url('/?registered=true')]);
                    exit;
                }
                redirect('/?registered=true');
            }
        } else {
            if ($isAjax) {
                while (ob_get_level()) ob_end_clean();
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'errors' => ['Invalid action']]);
                exit;
            }
            redirect('/');
        }
    } else {
        redirect('/');
    }
} catch (Throwable $e) {
    error_log('auth-handler error: ' . $e->getMessage());
    if (isset($isAjax) && $isAjax) {
        while (ob_get_level()) ob_end_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'errors' => ['An unexpected error occurred.']]);
        exit;
    }
    $_SESSION['flash_login_errors'] = ['An unexpected error occurred.'];
    redirect('/');
}
