import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../config/theme.dart';
import '../../providers/auth_provider.dart';
import 'widgets/auth_header.dart';
import 'widgets/auth_styles.dart';

class ForgotPasswordPage extends StatefulWidget {
  const ForgotPasswordPage({super.key});

  @override
  State<ForgotPasswordPage> createState() => _ForgotPasswordPageState();
}

class _ForgotPasswordPageState extends State<ForgotPasswordPage> {
  final _emailCtrl = TextEditingController();
  final _codeCtrl = TextEditingController();
  final _newPasswordCtrl = TextEditingController();
  final _confirmPasswordCtrl = TextEditingController();

  bool _codeSent = false;
  bool _obscurePassword = true;
  bool _obscureConfirmPassword = true;

  @override
  void dispose() {
    _emailCtrl.dispose();
    _codeCtrl.dispose();
    _newPasswordCtrl.dispose();
    _confirmPasswordCtrl.dispose();
    super.dispose();
  }

  void _requestCode() async {
    final email = _emailCtrl.text.trim();
    if (email.isEmpty || !email.contains('@') || !email.contains('.')) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Please enter a valid email address.'),
          backgroundColor: Colors.red,
        ),
      );
      return;
    }

    final auth = context.read<AuthProvider>();
    auth.clearError();

    final success = await auth.forgotPassword(email);

    if (!mounted) return;

    if (success) {
      setState(() => _codeSent = true);
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Recovery code sent! Please check your email inbox.'),
          backgroundColor: Colors.green,
        ),
      );
    }
  }

  void _resetPassword() async {
    final code = _codeCtrl.text.trim();
    final newPass = _newPasswordCtrl.text;
    final confirmPass = _confirmPasswordCtrl.text;

    if (code.length != 6) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Please enter the complete 6-digit recovery code.'),
          backgroundColor: Colors.red,
        ),
      );
      return;
    }

    if (newPass.length < 8) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('New password must be at least 8 characters.'),
          backgroundColor: Colors.red,
        ),
      );
      return;
    }

    if (newPass != confirmPass) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Passwords do not match.'),
          backgroundColor: Colors.red,
        ),
      );
      return;
    }

    final auth = context.read<AuthProvider>();
    auth.clearError();

    final success = await auth.resetPassword(_emailCtrl.text.trim(), code, newPass);

    if (!mounted) return;

    if (success) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Password reset successfully! You are now logged in.'),
          backgroundColor: Colors.green,
        ),
      );
      Navigator.of(context).popUntil((route) => route.isFirst);
    }
  }

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthProvider>();

    return Scaffold(
      backgroundColor: const Color(0xFFF8F9FD),
      body: SingleChildScrollView(
        child: Column(
          children: [
            AuthHeader(
              title: _codeSent ? 'Enter Recovery Code' : 'Reset Password',
              subtitle: _codeSent
                  ? 'Verify code and set your new password'
                  : 'We will send a 6-digit code to your email',
              showBack: true,
              headerHeight: 210,
            ),

            Transform.translate(
              offset: const Offset(0, -26),
              child: Container(
                margin: const EdgeInsets.symmetric(horizontal: 16),
                padding: const EdgeInsets.fromLTRB(22, 26, 22, 24),
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
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    // Icon Badge Header
                    Center(
                      child: Container(
                        padding: const EdgeInsets.all(14),
                        decoration: BoxDecoration(
                          color: AppTheme.primarySoft,
                          shape: BoxShape.circle,
                          border: Border.all(
                            color: AppTheme.primary.withValues(alpha: 0.2),
                            width: 1.5,
                          ),
                        ),
                        child: Icon(
                          _codeSent ? Icons.lock_open_rounded : Icons.lock_reset_rounded,
                          size: 36,
                          color: AppTheme.primary,
                        ),
                      ),
                    ),
                    const SizedBox(height: 14),

                    Text(
                      _codeSent ? 'Create New Password' : 'Forgot Password',
                      textAlign: TextAlign.center,
                      style: const TextStyle(
                        fontSize: 18,
                        fontWeight: FontWeight.bold,
                        color: AppTheme.ink,
                      ),
                    ),
                    const SizedBox(height: 6),
                    Text(
                      _codeSent
                          ? 'Enter the 6-digit code sent to ${_emailCtrl.text.trim()} and your new password.'
                          : 'Enter your registered email address and we will send you a verification code.',
                      textAlign: TextAlign.center,
                      style: const TextStyle(fontSize: 13, color: AppTheme.muted, height: 1.35),
                    ),
                    const SizedBox(height: 22),

                    if (auth.errorMessage != null)
                      AuthStyles.errorBanner(auth.errorMessage!),

                    // Step 1: Email Form
                    if (!_codeSent) ...[
                      TextField(
                        controller: _emailCtrl,
                        keyboardType: TextInputType.emailAddress,
                        textInputAction: TextInputAction.done,
                        onSubmitted: (_) => _requestCode(),
                        decoration: AuthStyles.inputDecoration(
                          label: 'Email Address',
                          hint: 'name@example.com',
                          prefixIcon: Icons.email_outlined,
                        ),
                      ),
                      const SizedBox(height: 22),

                      AuthStyles.gradientButton(
                        onPressed: _requestCode,
                        text: 'Send Recovery Code',
                        isLoading: auth.isLoading,
                        trailingIcon: Icons.arrow_forward_rounded,
                      ),
                      const SizedBox(height: 16),

                      Center(
                        child: TextButton.icon(
                          onPressed: () => Navigator.of(context).pop(),
                          icon: const Icon(Icons.arrow_back, size: 16, color: AppTheme.muted),
                          label: const Text(
                            'Back to Sign In',
                            style: TextStyle(color: AppTheme.muted, fontSize: 13, fontWeight: FontWeight.w600),
                          ),
                        ),
                      ),
                    ]
                    // Step 2: Code & New Password Form
                    else ...[
                      // Code input
                      TextField(
                        controller: _codeCtrl,
                        keyboardType: TextInputType.number,
                        maxLength: 6,
                        textAlign: TextAlign.center,
                        style: const TextStyle(
                          fontSize: 24,
                          letterSpacing: 10,
                          fontWeight: FontWeight.bold,
                          color: AppTheme.primary,
                        ),
                        decoration: AuthStyles.inputDecoration(
                          label: '6-Digit Code',
                          hint: '000000',
                          prefixIcon: Icons.pin_rounded,
                        ).copyWith(counterText: ''),
                      ),
                      const SizedBox(height: 16),

                      // New Password
                      TextField(
                        controller: _newPasswordCtrl,
                        obscureText: _obscurePassword,
                        decoration: AuthStyles.inputDecoration(
                          label: 'New Password',
                          hint: 'Min 8 characters',
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
                      ),
                      const SizedBox(height: 16),

                      // Confirm Password
                      TextField(
                        controller: _confirmPasswordCtrl,
                        obscureText: _obscureConfirmPassword,
                        decoration: AuthStyles.inputDecoration(
                          label: 'Confirm New Password',
                          hint: 'Re-enter your new password',
                          prefixIcon: Icons.lock_reset_rounded,
                          suffixIcon: IconButton(
                            icon: Icon(
                              _obscureConfirmPassword ? Icons.visibility_outlined : Icons.visibility_off_outlined,
                              color: const Color(0xFF718096),
                              size: 20,
                            ),
                            onPressed: () => setState(() => _obscureConfirmPassword = !_obscureConfirmPassword),
                          ),
                        ),
                      ),
                      const SizedBox(height: 22),

                      AuthStyles.gradientButton(
                        onPressed: _resetPassword,
                        text: 'Reset Password & Sign In',
                        isLoading: auth.isLoading,
                        trailingIcon: Icons.check_circle_outline_rounded,
                      ),
                      const SizedBox(height: 14),

                      Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          TextButton(
                            onPressed: () => setState(() => _codeSent = false),
                            child: const Text(
                              'Change Email',
                              style: TextStyle(color: AppTheme.muted, fontSize: 13),
                            ),
                          ),
                          TextButton(
                            onPressed: auth.isLoading ? null : _requestCode,
                            child: const Text(
                              'Resend Code',
                              style: TextStyle(
                                color: AppTheme.primary,
                                fontSize: 13,
                                fontWeight: FontWeight.bold,
                              ),
                            ),
                          ),
                        ],
                      ),
                    ],
                  ],
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
