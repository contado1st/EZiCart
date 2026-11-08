import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../config/api_config.dart';
import '../../config/theme.dart';
import '../../providers/auth_provider.dart';
import 'forgot_password_page.dart';
import 'otp_verification_page.dart';
import 'register_page.dart';
import 'widgets/auth_header.dart';
import 'widgets/auth_styles.dart';

class LoginPage extends StatefulWidget {
  const LoginPage({super.key});

  @override
  State<LoginPage> createState() => _LoginPageState();
}

class _LoginPageState extends State<LoginPage> {
  final _formKey = GlobalKey<FormState>();
  final _emailCtrl = TextEditingController();
  final _passwordCtrl = TextEditingController();
  bool _obscurePassword = true;

  @override
  void dispose() {
    _emailCtrl.dispose();
    _passwordCtrl.dispose();
    super.dispose();
  }

  void _submit() async {
    if (!_formKey.currentState!.validate()) return;
    final auth = context.read<AuthProvider>();
    auth.clearError();

    final success = await auth.login(
      _emailCtrl.text.trim(),
      _passwordCtrl.text,
    );

    if (!mounted) return;

    if (!success) {
      if (auth.pendingVerificationEmail != null) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Please verify your email address to continue.'),
            backgroundColor: Colors.orange,
          ),
        );
        Navigator.of(context).push(
          MaterialPageRoute(
            builder: (_) => OtpVerificationPage(
              email: auth.pendingVerificationEmail!,
            ),
          ),
        );
      }
    }
  }

  void _showDomainConfigDialog() {
    final domainCtrl = TextEditingController(text: ApiConfig.baseUrl);
    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
        title: const Row(
          children: [
            Icon(Icons.tune, color: AppTheme.primary, size: 22),
            SizedBox(width: 8),
            Text('Server Settings', style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
          ],
        ),
        content: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Text(
                'Configure the Laravel backend API endpoint:',
                style: TextStyle(fontSize: 13, color: AppTheme.muted),
              ),
              const SizedBox(height: 12),
              TextField(
                controller: domainCtrl,
                decoration: AuthStyles.inputDecoration(
                  label: 'API Base URL',
                  hint: 'http://127.0.0.1:8000/api',
                  prefixIcon: Icons.link_rounded,
                ),
              ),
              const SizedBox(height: 16),
              const Text(
                'Quick Presets:',
                style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold, color: AppTheme.ink),
              ),
              const SizedBox(height: 8),
              Wrap(
                spacing: 8,
                runSpacing: 8,
                children: [
                  ActionChip(
                    backgroundColor: const Color(0xFFF1F5F9),
                    label: const Text('127.0.0.1 (Local PC/Web)', style: TextStyle(fontSize: 11)),
                    onPressed: () => domainCtrl.text = 'http://127.0.0.1:8000/api',
                  ),
                  ActionChip(
                    backgroundColor: const Color(0xFFF1F5F9),
                    label: const Text('10.0.2.2 (Android Emulator)', style: TextStyle(fontSize: 11)),
                    onPressed: () => domainCtrl.text = 'http://10.0.2.2:8000/api',
                  ),
                  ActionChip(
                    backgroundColor: const Color(0xFFF1F5F9),
                    label: const Text('Live Server', style: TextStyle(fontSize: 11)),
                    onPressed: () => domainCtrl.text = 'https://shop.ezicart.trade/api/v1',
                  ),
                ],
              ),
            ],
          ),
        ),
        actions: [
          TextButton(
            onPressed: () async {
              final nav = Navigator.of(context);
              await ApiConfig.resetBaseUrl();
              if (!mounted) return;
              nav.pop();
              ScaffoldMessenger.of(context).showSnackBar(
                const SnackBar(content: Text('Reset to default base URL.')),
              );
            },
            child: const Text('Reset Default', style: TextStyle(color: AppTheme.muted)),
          ),
          ElevatedButton(
            style: ElevatedButton.styleFrom(
              backgroundColor: AppTheme.primary,
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
            ),
            onPressed: () async {
              final nav = Navigator.of(context);
              if (domainCtrl.text.trim().isNotEmpty) {
                await ApiConfig.setBaseUrl(domainCtrl.text.trim());
              }
              if (!mounted) return;
              nav.pop();
              ScaffoldMessenger.of(context).showSnackBar(
                const SnackBar(content: Text('API URL updated successfully.')),
              );
            },
            child: const Text('Save Domain', style: TextStyle(color: Colors.white)),
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthProvider>();

    return Scaffold(
      backgroundColor: const Color(0xFFF8F9FD),
      body: SingleChildScrollView(
        child: Column(
          children: [
            // Modern Branded Hero Header
            AuthHeader(
              title: 'Welcome Back',
              subtitle: 'Sign in to access your orders and shopping cart',
              headerHeight: 220,
              topAction: Material(
                color: Colors.white.withValues(alpha: 0.18),
                borderRadius: BorderRadius.circular(12),
                child: InkWell(
                  borderRadius: BorderRadius.circular(12),
                  onTap: _showDomainConfigDialog,
                  child: const Padding(
                    padding: EdgeInsets.all(8),
                    child: Icon(Icons.tune_rounded, color: Colors.white, size: 20),
                  ),
                ),
              ),
            ),

            // Elevated Card Form
            Transform.translate(
              offset: const Offset(0, -26),
              child: Container(
                margin: const EdgeInsets.symmetric(horizontal: 16),
                padding: const EdgeInsets.fromLTRB(22, 28, 22, 24),
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(24),
                  boxShadow: [
                    BoxShadow(
                      color: Colors.black.withValues(alpha: 0.06),
                      blurRadius: 20,
                      offset: const Offset(0, 10),
                    ),
                  ],
                ),
                child: Form(
                  key: _formKey,
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      // Card Header
                      Row(
                        children: [
                          Container(
                            padding: const EdgeInsets.all(8),
                            decoration: BoxDecoration(
                              color: AppTheme.primarySoft,
                              borderRadius: BorderRadius.circular(10),
                            ),
                            child: const Icon(Icons.lock_person_outlined, color: AppTheme.primary, size: 20),
                          ),
                          const SizedBox(width: 10),
                          const Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                'Sign In as Buyer',
                                style: TextStyle(
                                  fontSize: 18,
                                  fontWeight: FontWeight.bold,
                                  color: AppTheme.ink,
                                ),
                              ),
                              Text(
                                'Enter your account details below',
                                style: TextStyle(fontSize: 12, color: AppTheme.muted),
                              ),
                            ],
                          ),
                        ],
                      ),
                      const SizedBox(height: 22),

                      if (auth.errorMessage != null)
                        AuthStyles.errorBanner(auth.errorMessage!),

                      // Email Field
                      TextFormField(
                        controller: _emailCtrl,
                        keyboardType: TextInputType.emailAddress,
                        textInputAction: TextInputAction.next,
                        decoration: AuthStyles.inputDecoration(
                          label: 'Email Address',
                          hint: 'name@example.com',
                          prefixIcon: Icons.email_outlined,
                        ),
                        validator: (v) {
                          if (v == null || v.trim().isEmpty) return 'Please enter your email.';
                          if (!v.contains('@') || !v.contains('.')) return 'Enter a valid email address.';
                          return null;
                        },
                      ),
                      const SizedBox(height: 16),

                      // Password Field
                      TextFormField(
                        controller: _passwordCtrl,
                        obscureText: _obscurePassword,
                        textInputAction: TextInputAction.done,
                        onFieldSubmitted: (_) => _submit(),
                        decoration: AuthStyles.inputDecoration(
                          label: 'Password',
                          hint: 'Enter your password',
                          prefixIcon: Icons.lock_outline_rounded,
                          suffixIcon: IconButton(
                            icon: Icon(
                              _obscurePassword ? Icons.visibility_outlined : Icons.visibility_off_outlined,
                              color: const Color(0xFF718096),
                              size: 20,
                            ),
                            onPressed: () => setState(() => _obscurePassword = !_obscurePassword),
                          ),
                        ),
                        validator: (v) => (v == null || v.isEmpty) ? 'Please enter your password.' : null,
                      ),
                      const SizedBox(height: 8),

                      // Forgot Password Link
                      Align(
                        alignment: Alignment.centerRight,
                        child: TextButton(
                          style: TextButton.styleFrom(
                            padding: const EdgeInsets.symmetric(horizontal: 4, vertical: 4),
                            minimumSize: Size.zero,
                            tapTargetSize: MaterialTapTargetSize.shrinkWrap,
                          ),
                          onPressed: () {
                            Navigator.of(context).push(
                              MaterialPageRoute(builder: (_) => const ForgotPasswordPage()),
                            );
                          },
                          child: const Text(
                            'Forgot Password?',
                            style: TextStyle(
                              fontSize: 13,
                              fontWeight: FontWeight.w600,
                              color: AppTheme.primary,
                            ),
                          ),
                        ),
                      ),
                      const SizedBox(height: 20),

                      // Submit Button
                      AuthStyles.gradientButton(
                        onPressed: _submit,
                        text: 'Sign In as Buyer',
                        isLoading: auth.isLoading,
                        trailingIcon: Icons.arrow_forward_rounded,
                      ),
                      const SizedBox(height: 24),

                      // Register Callout
                      Row(
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: [
                          const Text(
                            "Don't have an account? ",
                            style: TextStyle(color: AppTheme.muted, fontSize: 13),
                          ),
                          GestureDetector(
                            onTap: () {
                              Navigator.of(context).push(
                                MaterialPageRoute(builder: (_) => const RegisterPage()),
                              );
                            },
                            child: const Text(
                              'Register Here',
                              style: TextStyle(
                                color: AppTheme.primary,
                                fontWeight: FontWeight.bold,
                                fontSize: 13,
                              ),
                            ),
                          ),
                        ],
                      ),
                    ],
                  ),
                ),
              ),
            ),
            const SizedBox(height: 12),
          ],
        ),
      ),
    );
  }
}
