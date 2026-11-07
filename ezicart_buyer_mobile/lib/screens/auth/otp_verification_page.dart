import 'dart:async';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../config/theme.dart';
import '../../providers/auth_provider.dart';

class OtpVerificationPage extends StatefulWidget {
  final String email;

  const OtpVerificationPage({super.key, required this.email});

  @override
  State<OtpVerificationPage> createState() => _OtpVerificationPageState();
}

class _OtpVerificationPageState extends State<OtpVerificationPage> {
  final _codeCtrl = TextEditingController();
  int _countdown = 60;
  Timer? _timer;
  bool _isResending = false;

  @override
  void initState() {
    super.initState();
    _startCountdown();
  }

  @override
  void dispose() {
    _timer?.cancel();
    _codeCtrl.dispose();
    super.dispose();
  }

  void _startCountdown() {
    setState(() => _countdown = 60);
    _timer?.cancel();
    _timer = Timer.periodic(const Duration(seconds: 1), (t) {
      if (_countdown > 0) {
        setState(() => _countdown--);
      } else {
        t.cancel();
      }
    });
  }

  void _verify() async {
    final code = _codeCtrl.text.trim();
    if (code.length != 6) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Please enter the complete 6-digit OTP code.')),
      );
      return;
    }

    final auth = context.read<AuthProvider>();
    auth.clearError();

    final success = await auth.verifyOtp(widget.email, code);

    if (!mounted) return;

    if (success) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Email verified! Welcome to EZiCart.'), backgroundColor: Colors.green),
      );
      Navigator.of(context).popUntil((route) => route.isFirst);
    }
  }

  void _resend() async {
    if (_countdown > 0 || _isResending) return;
    setState(() => _isResending = true);

    final auth = context.read<AuthProvider>();
    final success = await auth.resendOtp(widget.email);

    if (!mounted) return;
    setState(() => _isResending = false);

    if (success) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('A fresh 6-digit code has been sent to your email.'), backgroundColor: Colors.green),
      );
      _startCountdown();
    }
  }

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthProvider>();

    return Scaffold(
      appBar: AppBar(
        title: const Text('Email Verification'),
      ),
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              const SizedBox(height: 12),
              Center(
                child: Container(
                  padding: const EdgeInsets.all(16),
                  decoration: const BoxDecoration(
                    color: AppTheme.primarySoft,
                    shape: BoxShape.circle,
                  ),
                  child: const Icon(Icons.mark_email_read_outlined, size: 48, color: AppTheme.primary),
                ),
              ),
              const SizedBox(height: 20),
              const Text(
                'Enter Verification Code',
                textAlign: TextAlign.center,
                style: TextStyle(fontSize: 20, fontWeight: FontWeight.bold, color: AppTheme.ink),
              ),
              const SizedBox(height: 8),
              Text(
                'We have sent a 6-digit code to\n${widget.email}',
                textAlign: TextAlign.center,
                style: const TextStyle(fontSize: 13, color: AppTheme.muted, height: 1.4),
              ),
              const SizedBox(height: 28),

              if (auth.errorMessage != null)
                Container(
                  margin: const EdgeInsets.only(bottom: 20),
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(
                    color: Colors.red.shade50,
                    border: Border.all(color: Colors.red.shade200),
                    borderRadius: BorderRadius.circular(7),
                  ),
                  child: Text(
                    auth.errorMessage!,
                    textAlign: TextAlign.center,
                    style: TextStyle(color: Colors.red.shade900, fontSize: 13),
                  ),
                ),

              TextField(
                controller: _codeCtrl,
                keyboardType: TextInputType.number,
                maxLength: 6,
                textAlign: TextAlign.center,
                style: const TextStyle(fontSize: 24, letterSpacing: 10, fontWeight: FontWeight.bold),
                decoration: const InputDecoration(
                  counterText: '',
                  hintText: '000000',
                  hintStyle: TextStyle(fontSize: 24, letterSpacing: 10, color: AppTheme.muted),
                ),
              ),
              const SizedBox(height: 24),

              ElevatedButton(
                onPressed: auth.isLoading ? null : _verify,
                child: auth.isLoading
                    ? const SizedBox(
                        width: 20,
                        height: 20,
                        child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
                      )
                    : const Text('Verify & Continue'),
              ),
              const SizedBox(height: 20),

              Center(
                child: TextButton(
                  onPressed: _countdown == 0 ? _resend : null,
                  child: Text(
                    _countdown > 0 ? 'Resend code in ${_countdown}s' : 'Resend Code',
                    style: TextStyle(
                      color: _countdown == 0 ? AppTheme.primary : AppTheme.muted,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
