import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../config/api_config.dart';
import '../../config/theme.dart';
import '../../providers/auth_provider.dart';
import 'widgets/auth_scaffold.dart';
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

    await auth.login(
      _emailCtrl.text.trim(),
      _passwordCtrl.text,
    );
  }

  void _showDomainConfigDialog() {
    final domainCtrl = TextEditingController(text: ApiConfig.baseUrl);
    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
        title: const Row(
          children: [
            Icon(Icons.tune_rounded, color: AppTheme.primary, size: 22),
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
                autocorrect: false,
                enableSuggestions: false,
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
                    label: const Text('127.0.0.1 (Local PC)', style: TextStyle(fontSize: 11)),
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

    return AuthScaffold(
      title: 'Courier & Driver Portal',
      subtitle: 'Sign in to manage pickups, hub transfers, and deliveries',
      topAction: Material(
        color: Colors.black.withValues(alpha: 0.30),
        borderRadius: BorderRadius.circular(12),
        child: InkWell(
          borderRadius: BorderRadius.circular(12),
          onTap: _showDomainConfigDialog,
          child: Container(
            padding: const EdgeInsets.all(8),
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(12),
              border: Border.all(
                color: Colors.white.withValues(alpha: 0.30),
                width: 1,
              ),
            ),
            child: const Icon(Icons.tune_rounded, color: Colors.white, size: 18),
          ),
        ),
      ),
      cardChild: Form(
        key: _formKey,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            if (auth.errorMessage != null)
              AuthStyles.errorBanner(auth.errorMessage!),

            // Email Field
            TextFormField(
              controller: _emailCtrl,
              keyboardType: TextInputType.emailAddress,
              autocorrect: false,
              enableSuggestions: false,
              textInputAction: TextInputAction.next,
              decoration: AuthStyles.inputDecoration(
                label: 'Courier Email Address',
                hint: 'rider@ezicart.trade',
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
              autocorrect: false,
              enableSuggestions: false,
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
            const SizedBox(height: 24),

            // Submit Button
            AuthStyles.gradientButton(
              onPressed: _submit,
              text: 'Sign In as Driver / Courier',
              isLoading: auth.isLoading,
              trailingIcon: Icons.arrow_forward_rounded,
            ),
          ],
        ),
      ),
      // No registration cardBottom as required: "Remove any registration/sign-up links or screens"
      cardBottom: Container(
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
        child: const Text(
          'Driver and Courier accounts are provisioned by Logistics Administration.',
          textAlign: TextAlign.center,
          style: TextStyle(
            color: Colors.white70,
            fontSize: 12,
            fontWeight: FontWeight.w400,
          ),
        ),
      ),
    );
  }
}
