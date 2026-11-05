import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:provider/provider.dart';
import '../../config/theme.dart';
import '../../providers/auth_provider.dart';
import 'otp_verification_page.dart';

class RegisterPage extends StatefulWidget {
  const RegisterPage({super.key});

  @override
  State<RegisterPage> createState() => _RegisterPageState();
}

class _RegisterPageState extends State<RegisterPage> {
  final _formKey = GlobalKey<FormState>();

  final _firstNameCtrl = TextEditingController();
  final _middleNameCtrl = TextEditingController();
  final _lastNameCtrl = TextEditingController();
  final _suffixCtrl = TextEditingController();
  final _emailCtrl = TextEditingController();
  final _contactCtrl = TextEditingController();
  final _birthdayCtrl = TextEditingController();
  final _passwordCtrl = TextEditingController();
  final _confirmPasswordCtrl = TextEditingController();

  final _provinceCtrl = TextEditingController(text: 'Laguna');
  final _muniCtrl = TextEditingController(text: 'Lumban');
  final _brgyCtrl = TextEditingController(text: 'Poblacion');
  final _streetCtrl = TextEditingController();
  final _postalCtrl = TextEditingController();

  bool _obscurePassword = true;
  bool _termsAccepted = false;
  DateTime? _selectedBirthday;

  @override
  void dispose() {
    _firstNameCtrl.dispose();
    _middleNameCtrl.dispose();
    _lastNameCtrl.dispose();
    _suffixCtrl.dispose();
    _emailCtrl.dispose();
    _contactCtrl.dispose();
    _birthdayCtrl.dispose();
    _passwordCtrl.dispose();
    _confirmPasswordCtrl.dispose();
    _provinceCtrl.dispose();
    _muniCtrl.dispose();
    _brgyCtrl.dispose();
    _streetCtrl.dispose();
    _postalCtrl.dispose();
    super.dispose();
  }

  Future<void> _selectBirthday() async {
    final now = DateTime.now();
    final maxDate = DateTime(now.year - 18, now.month, now.day);
    final initial = _selectedBirthday ?? DateTime(now.year - 20, now.month, now.day);

    final picked = await showDatePicker(
      context: context,
      initialDate: initial,
      firstDate: DateTime(1920),
      lastDate: maxDate,
      helpText: 'Select Birthday (Must be at least 18 years old)',
    );

    if (picked != null) {
      setState(() {
        _selectedBirthday = picked;
        _birthdayCtrl.text = DateFormat('yyyy-MM-dd').format(picked);
      });
    }
  }

  void _submit() async {
    if (!_formKey.currentState!.validate()) return;

    if (!_termsAccepted) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Please accept the Terms & Conditions and Privacy Policy.')),
      );
      return;
    }

    final auth = context.read<AuthProvider>();
    auth.clearError();

    final success = await auth.register(
      firstName: _firstNameCtrl.text.trim(),
      middleName: _middleNameCtrl.text.trim().isNotEmpty ? _middleNameCtrl.text.trim() : null,
      lastName: _lastNameCtrl.text.trim(),
      suffix: _suffixCtrl.text.trim().isNotEmpty ? _suffixCtrl.text.trim() : null,
      email: _emailCtrl.text.trim(),
      contactNo: _contactCtrl.text.trim(),
      birthday: _birthdayCtrl.text.trim(),
      password: _passwordCtrl.text,
      region: 'Region IV-A (CALABARZON)',
      province: _provinceCtrl.text.trim(),
      municipality: _muniCtrl.text.trim(),
      barangay: _brgyCtrl.text.trim(),
      streetAddress: _streetCtrl.text.trim().isNotEmpty ? _streetCtrl.text.trim() : null,
      postalCode: _postalCtrl.text.trim().isNotEmpty ? _postalCtrl.text.trim() : null,
    );

    if (!mounted) return;

    if (success) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Account created! A 6-digit verification code has been sent to your email.'),
          backgroundColor: Colors.green,
        ),
      );
      Navigator.of(context).pushReplacement(
        MaterialPageRoute(
          builder: (_) => OtpVerificationPage(
            email: _emailCtrl.text.trim(),
          ),
        ),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthProvider>();

    return Scaffold(
      appBar: AppBar(
        title: const Text('Buyer Registration'),
      ),
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(20),
          child: Form(
            key: _formKey,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                const Text(
                  'Create your Buyer Account',
                  style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold, color: AppTheme.ink),
                ),
                const SizedBox(height: 4),
                const Text(
                  'Join EZiCart to discover local products with doorstep delivery.',
                  style: TextStyle(fontSize: 13, color: AppTheme.muted),
                ),
                const SizedBox(height: 20),

                if (auth.errorMessage != null)
                  Container(
                    margin: const EdgeInsets.only(bottom: 16),
                    padding: const EdgeInsets.all(12),
                    decoration: BoxDecoration(
                      color: Colors.red.shade50,
                      border: Border.all(color: Colors.red.shade200),
                      borderRadius: BorderRadius.circular(7),
                    ),
                    child: Text(
                      auth.errorMessage!,
                      style: TextStyle(color: Colors.red.shade900, fontSize: 13),
                    ),
                  ),

                // Name breakdown
                Row(
                  children: [
                    Expanded(
                      flex: 2,
                      child: TextFormField(
                        controller: _firstNameCtrl,
                        decoration: const InputDecoration(labelText: 'First Name *'),
                        validator: (v) => (v == null || v.trim().length < 2) ? 'Required' : null,
                      ),
                    ),
                    const SizedBox(width: 8),
                    Expanded(
                      flex: 1,
                      child: TextFormField(
                        controller: _middleNameCtrl,
                        decoration: const InputDecoration(labelText: 'M.I.'),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 12),

                Row(
                  children: [
                    Expanded(
                      flex: 3,
                      child: TextFormField(
                        controller: _lastNameCtrl,
                        decoration: const InputDecoration(labelText: 'Last Name *'),
                        validator: (v) => (v == null || v.trim().length < 2) ? 'Required' : null,
                      ),
                    ),
                    const SizedBox(width: 8),
                    Expanded(
                      flex: 1,
                      child: TextFormField(
                        controller: _suffixCtrl,
                        decoration: const InputDecoration(labelText: 'Suffix (Jr)'),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 12),

                TextFormField(
                  controller: _emailCtrl,
                  keyboardType: TextInputType.emailAddress,
                  decoration: const InputDecoration(labelText: 'Email Address *', prefixIcon: Icon(Icons.email_outlined, size: 20)),
                  validator: (v) {
                    if (v == null || v.trim().isEmpty) return 'Email is required';
                    if (!v.contains('@')) return 'Enter valid email';
                    return null;
                  },
                ),
                const SizedBox(height: 12),

                TextFormField(
                  controller: _contactCtrl,
                  keyboardType: TextInputType.phone,
                  decoration: const InputDecoration(
                    labelText: 'Mobile Number (09XXXXXXXXX) *',
                    prefixIcon: Icon(Icons.phone_android_outlined, size: 20),
                  ),
                  validator: (v) {
                    if (v == null || !RegExp(r'^(09\d{9}|\+639\d{9})$').hasMatch(v.trim())) {
                      return 'Enter a valid Philippine 11-digit mobile number';
                    }
                    return null;
                  },
                ),
                const SizedBox(height: 12),

                TextFormField(
                  controller: _birthdayCtrl,
                  readOnly: true,
                  onTap: _selectBirthday,
                  decoration: const InputDecoration(
                    labelText: 'Birthday *',
                    prefixIcon: Icon(Icons.cake_outlined, size: 20),
                    suffixIcon: Icon(Icons.calendar_month_outlined, size: 20),
                  ),
                  validator: (v) => (v == null || v.trim().isEmpty) ? 'Please select your birthday' : null,
                ),
                const SizedBox(height: 12),

                // Location fields
                Row(
                  children: [
                    Expanded(
                      child: TextFormField(
                        controller: _provinceCtrl,
                        decoration: const InputDecoration(labelText: 'Province *'),
                        validator: (v) => (v == null || v.trim().isEmpty) ? 'Required' : null,
                      ),
                    ),
                    const SizedBox(width: 8),
                    Expanded(
                      child: TextFormField(
                        controller: _muniCtrl,
                        decoration: const InputDecoration(labelText: 'Municipality *'),
                        validator: (v) => (v == null || v.trim().isEmpty) ? 'Required' : null,
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 12),

                Row(
                  children: [
                    Expanded(
                      child: TextFormField(
                        controller: _brgyCtrl,
                        decoration: const InputDecoration(labelText: 'Barangay *'),
                        validator: (v) => (v == null || v.trim().isEmpty) ? 'Required' : null,
                      ),
                    ),
                    const SizedBox(width: 8),
                    Expanded(
                      child: TextFormField(
                        controller: _postalCtrl,
                        keyboardType: TextInputType.number,
                        decoration: const InputDecoration(labelText: 'Postal Code'),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 12),

                TextFormField(
                  controller: _streetCtrl,
                  decoration: const InputDecoration(labelText: 'Street / Building / House No.'),
                ),
                const SizedBox(height: 12),

                // Password fields
                TextFormField(
                  controller: _passwordCtrl,
                  obscureText: _obscurePassword,
                  decoration: InputDecoration(
                    labelText: 'Password (min 8 chars) *',
                    prefixIcon: const Icon(Icons.lock_outline, size: 20),
                    suffixIcon: IconButton(
                      icon: Icon(_obscurePassword ? Icons.visibility_outlined : Icons.visibility_off_outlined),
                      onPressed: () => setState(() => _obscurePassword = !_obscurePassword),
                    ),
                  ),
                  validator: (v) {
                    if (v == null || v.length < 8) return 'Password must be at least 8 characters';
                    return null;
                  },
                ),
                const SizedBox(height: 12),

                TextFormField(
                  controller: _confirmPasswordCtrl,
                  obscureText: _obscurePassword,
                  decoration: const InputDecoration(
                    labelText: 'Confirm Password *',
                    prefixIcon: Icon(Icons.lock_reset_outlined, size: 20),
                  ),
                  validator: (v) {
                    if (v != _passwordCtrl.text) return 'Passwords do not match';
                    return null;
                  },
                ),
                const SizedBox(height: 16),

                CheckboxListTile(
                  contentPadding: EdgeInsets.zero,
                  value: _termsAccepted,
                  onChanged: (v) => setState(() => _termsAccepted = v ?? false),
                  title: const Text(
                    'I agree to the Terms of Service and Privacy Policy.',
                    style: TextStyle(fontSize: 13),
                  ),
                  controlAffinity: ListTileControlAffinity.leading,
                ),
                const SizedBox(height: 20),

                ElevatedButton(
                  onPressed: auth.isLoading ? null : _submit,
                  child: auth.isLoading
                      ? const SizedBox(
                          width: 20,
                          height: 20,
                          child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
                        )
                      : const Text('Create Buyer Account'),
                ),
                const SizedBox(height: 16),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
