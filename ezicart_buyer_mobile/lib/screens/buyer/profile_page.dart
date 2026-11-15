import 'dart:convert';
import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../config/api_config.dart';
import '../../config/theme.dart';
import '../../models/user_model.dart';
import '../../providers/auth_provider.dart';
import '../../services/api_service.dart';
import 'addresses_page.dart';
import 'shop_page.dart';
import 'wallet_page.dart';
import 'wishlist_page.dart';

class ProfilePage extends StatefulWidget {
  const ProfilePage({super.key});

  @override
  State<ProfilePage> createState() => _ProfilePageState();
}

class _ProfilePageState extends State<ProfilePage> {
  bool _promotionsEnabled = true;
  List<Map<String, dynamic>> _followedShops = [];
  bool _isLoadingFollowedShops = true;

  // Change Password controllers
  final _currentPasswordCtrl = TextEditingController();
  final _newPasswordCtrl = TextEditingController();
  final _confirmPasswordCtrl = TextEditingController();
  bool _obscureCurrent = true;
  bool _obscureNew = true;
  bool _obscureConfirm = true;
  bool _isUpdatingPassword = false;

  @override
  void initState() {
    super.initState();
    _loadPreferences();
    _loadFollowedShops();
  }

  @override
  void dispose() {
    _currentPasswordCtrl.dispose();
    _newPasswordCtrl.dispose();
    _confirmPasswordCtrl.dispose();
    super.dispose();
  }

  Future<void> _loadPreferences() async {
    try {
      final res = await ApiService.get(
        ApiConfig.notificationPreferencesEndpoint,
      );
      if (res.statusCode == 200) {
        final body = jsonDecode(res.body);
        if (body['seller_promotions_enabled'] is bool && mounted) {
          setState(
            () => _promotionsEnabled = body['seller_promotions_enabled'],
          );
        }
      }
    } catch (_) {}
  }

  Future<void> _loadFollowedShops() async {
    try {
      final res = await ApiService.get(ApiConfig.followedShopsEndpoint);
      if (res.statusCode == 200) {
        final body = jsonDecode(res.body);
        final rawList = body['shops'] ?? body['data'];
        if (rawList is List && mounted) {
          setState(() {
            _followedShops = List<Map<String, dynamic>>.from(rawList);
            _isLoadingFollowedShops = false;
          });
          return;
        }
      }
    } catch (_) {}
    if (mounted) setState(() => _isLoadingFollowedShops = false);
  }

  Future<void> _updatePromotions(bool value) async {
    setState(() => _promotionsEnabled = value);
    try {
      await ApiService.patch(ApiConfig.notificationPreferencesEndpoint, {
        'seller_promotions_enabled': value,
      });
    } catch (_) {}
  }

  Color _getThemeColor(String theme) {
    switch (theme.toLowerCase()) {
      case 'wine':
        return const Color(0xFF722F37);
      case 'navy':
        return const Color(0xFF1E3A8A);
      case 'teal':
        return const Color(0xFF0F766E);
      case 'rose':
      default:
        return const Color(0xFFBE2854);
    }
  }

  // 1. EDIT PROFILE APPEARANCE SHEET (Display Name, About Me, Theme, Avatar/Banner)
  void _showAppearanceSheet() {
    final auth = context.read<AuthProvider>();
    final user = auth.user;

    final displayNameCtrl = TextEditingController(
      text: user?.profileDisplayName ?? '',
    );
    final bioCtrl = TextEditingController(text: user?.profileBio ?? '');
    String selectedTheme = user?.profileTheme ?? 'rose';
    bool removeAvatar = false;
    bool removeBanner = false;
    bool isSaving = false;
    String? sheetError;

    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
      ),
      builder: (ctx) => StatefulBuilder(
        builder: (context, setSheetState) => Padding(
          padding: EdgeInsets.only(
            bottom: MediaQuery.of(context).viewInsets.bottom,
            top: 20,
            left: 20,
            right: 20,
          ),
          child: SingleChildScrollView(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    const Row(
                      children: [
                        Icon(
                          Icons.palette_outlined,
                          color: AppTheme.primary,
                          size: 22,
                        ),
                        SizedBox(width: 8),
                        Text(
                          'Profile Appearance',
                          style: TextStyle(
                            fontSize: 17,
                            fontWeight: FontWeight.bold,
                          ),
                        ),
                      ],
                    ),
                    IconButton(
                      icon: const Icon(Icons.close),
                      onPressed: isSaving ? null : () => Navigator.pop(ctx),
                    ),
                  ],
                ),
                const Text(
                  'Customize how your profile appears on EZiCart.',
                  style: TextStyle(fontSize: 12.5, color: AppTheme.muted),
                ),
                if (sheetError != null) ...[
                  const SizedBox(height: 10),
                  Container(
                    padding: const EdgeInsets.all(10),
                    decoration: BoxDecoration(
                      color: Colors.red.shade50,
                      borderRadius: BorderRadius.circular(8),
                      border: Border.all(color: Colors.red.shade200),
                    ),
                    child: Text(
                      sheetError!,
                      style: TextStyle(
                        fontSize: 12,
                        color: Colors.red.shade900,
                      ),
                    ),
                  ),
                ],
                const SizedBox(height: 16),

                // Display Name
                TextField(
                  controller: displayNameCtrl,
                  maxLength: 50,
                  decoration: InputDecoration(
                    labelText: 'Display Name',
                    hintText: user?.firstName ?? 'Your preferred nickname',
                    helperText: 'Optional. 2–50 characters.',
                  ),
                ),
                const SizedBox(height: 14),

                // Profile Theme Color Selector (Matching Web)
                Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text(
                      'Profile Color Preset',
                      style: TextStyle(
                        fontWeight: FontWeight.w600,
                        fontSize: 13,
                      ),
                    ),
                    const SizedBox(height: 8),
                    Row(
                      children: [
                        _buildThemeOption(
                          'rose',
                          'Rose',
                          const Color(0xFFBE2854),
                          selectedTheme,
                          (v) => setSheetState(() => selectedTheme = v),
                        ),
                        const SizedBox(width: 8),
                        _buildThemeOption(
                          'wine',
                          'Wine',
                          const Color(0xFF722F37),
                          selectedTheme,
                          (v) => setSheetState(() => selectedTheme = v),
                        ),
                        const SizedBox(width: 8),
                        _buildThemeOption(
                          'navy',
                          'Navy',
                          const Color(0xFF1E3A8A),
                          selectedTheme,
                          (v) => setSheetState(() => selectedTheme = v),
                        ),
                        const SizedBox(width: 8),
                        _buildThemeOption(
                          'teal',
                          'Teal',
                          const Color(0xFF0F766E),
                          selectedTheme,
                          (v) => setSheetState(() => selectedTheme = v),
                        ),
                      ],
                    ),
                  ],
                ),
                const SizedBox(height: 16),

                // About Me / Bio
                TextField(
                  controller: bioCtrl,
                  maxLength: 1000,
                  maxLines: 3,
                  decoration: const InputDecoration(
                    labelText: 'About Me',
                    hintText: 'A short introduction for your personal profile.',
                    helperText: 'Up to 1,000 characters plain text.',
                  ),
                ),
                const SizedBox(height: 14),

                // Reset photo / banner options if already present
                if (user?.hasAvatar == true || user?.hasBanner == true) ...[
                  if (user?.hasAvatar == true)
                    CheckboxListTile(
                      contentPadding: EdgeInsets.zero,
                      value: removeAvatar,
                      onChanged: (v) =>
                          setSheetState(() => removeAvatar = v ?? false),
                      title: const Text(
                        'Remove profile photo',
                        style: TextStyle(fontSize: 13),
                      ),
                      controlAffinity: ListTileControlAffinity.leading,
                    ),
                  if (user?.hasBanner == true)
                    CheckboxListTile(
                      contentPadding: EdgeInsets.zero,
                      value: removeBanner,
                      onChanged: (v) =>
                          setSheetState(() => removeBanner = v ?? false),
                      title: const Text(
                        'Remove cover banner',
                        style: TextStyle(fontSize: 13),
                      ),
                      controlAffinity: ListTileControlAffinity.leading,
                    ),
                ],
                const SizedBox(height: 20),

                ElevatedButton(
                  onPressed: isSaving
                      ? null
                      : () async {
                          final name = displayNameCtrl.text.trim();
                          if (name.isNotEmpty && name.length < 2) {
                            setSheetState(
                              () => sheetError =
                                  'Display name must be at least 2 characters.',
                            );
                            return;
                          }

                          setSheetState(() {
                            isSaving = true;
                            sheetError = null;
                          });

                          final scaffoldMessenger = ScaffoldMessenger.of(
                            context,
                          );
                          final success = await auth.updateAppearance(
                            displayName: name.isNotEmpty ? name : null,
                            bio: bioCtrl.text.trim().isNotEmpty
                                ? bioCtrl.text.trim()
                                : null,
                            theme: selectedTheme,
                            removeAvatar: removeAvatar,
                            removeBanner: removeBanner,
                          );

                          if (ctx.mounted) {
                            Navigator.pop(ctx);
                          }
                          scaffoldMessenger.showSnackBar(
                            SnackBar(
                              content: Text(
                                success
                                    ? 'Profile appearance updated successfully!'
                                    : (auth.errorMessage ??
                                          'Failed to update appearance.'),
                              ),
                              backgroundColor: success
                                  ? Colors.green
                                  : Colors.red,
                            ),
                          );
                        },
                  child: isSaving
                      ? const SizedBox(
                          width: 20,
                          height: 20,
                          child: CircularProgressIndicator(
                            strokeWidth: 2,
                            color: Colors.white,
                          ),
                        )
                      : const Text('Save Appearance'),
                ),
                const SizedBox(height: 24),
              ],
            ),
          ),
        ),
      ),
    );
  }

  Widget _buildThemeOption(
    String value,
    String label,
    Color color,
    String current,
    Function(String) onSelect,
  ) {
    final isSelected = current == value;
    return Expanded(
      child: InkWell(
        onTap: () => onSelect(value),
        borderRadius: BorderRadius.circular(8),
        child: Container(
          padding: const EdgeInsets.symmetric(vertical: 8),
          decoration: BoxDecoration(
            color: isSelected
                ? color.withValues(alpha: 0.12)
                : const Color(0xFFF8FAFC),
            borderRadius: BorderRadius.circular(8),
            border: Border.all(
              color: isSelected ? color : const Color(0xFFE2E8F0),
              width: isSelected ? 2 : 1,
            ),
          ),
          child: Column(
            children: [
              Container(
                width: 18,
                height: 18,
                decoration: BoxDecoration(color: color, shape: BoxShape.circle),
              ),
              const SizedBox(height: 4),
              Text(
                label,
                style: TextStyle(
                  fontSize: 11,
                  fontWeight: isSelected ? FontWeight.bold : FontWeight.w500,
                  color: isSelected ? color : AppTheme.ink,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  // 2. EDIT PROFILE DETAILS SHEET (First Name, Last Name, Contact Number)
  void _showEditProfileDialog() {
    final auth = context.read<AuthProvider>();
    final user = auth.user;

    final firstCtrl = TextEditingController(text: user?.firstName ?? '');
    final middleCtrl = TextEditingController(text: user?.middleName ?? '');
    final lastCtrl = TextEditingController(text: user?.lastName ?? '');
    final contactCtrl = TextEditingController(text: user?.contactNo ?? '');
    bool isSaving = false;
    String? dialogError;

    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
      ),
      builder: (ctx) => StatefulBuilder(
        builder: (context, setSheetState) => Padding(
          padding: EdgeInsets.only(
            bottom: MediaQuery.of(context).viewInsets.bottom,
            top: 20,
            left: 20,
            right: 20,
          ),
          child: SingleChildScrollView(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    const Row(
                      children: [
                        Icon(
                          Icons.badge_outlined,
                          color: AppTheme.primary,
                          size: 22,
                        ),
                        SizedBox(width: 8),
                        Text(
                          'Profile Details',
                          style: TextStyle(
                            fontSize: 17,
                            fontWeight: FontWeight.bold,
                          ),
                        ),
                      ],
                    ),
                    IconButton(
                      icon: const Icon(Icons.close),
                      onPressed: isSaving ? null : () => Navigator.pop(ctx),
                    ),
                  ],
                ),
                const Text(
                  'Update the legal name and contact number connected to your account.',
                  style: TextStyle(fontSize: 12.5, color: AppTheme.muted),
                ),
                if (dialogError != null) ...[
                  const SizedBox(height: 10),
                  Container(
                    padding: const EdgeInsets.all(10),
                    decoration: BoxDecoration(
                      color: Colors.red.shade50,
                      borderRadius: BorderRadius.circular(8),
                      border: Border.all(color: Colors.red.shade200),
                    ),
                    child: Text(
                      dialogError!,
                      style: TextStyle(
                        fontSize: 12,
                        color: Colors.red.shade900,
                      ),
                    ),
                  ),
                ],
                const SizedBox(height: 16),

                // First Name & Middle Initial
                Row(
                  children: [
                    Expanded(
                      flex: 2,
                      child: TextField(
                        controller: firstCtrl,
                        textCapitalization: TextCapitalization.words,
                        decoration: const InputDecoration(
                          labelText: 'First Name *',
                        ),
                      ),
                    ),
                    const SizedBox(width: 8),
                    Expanded(
                      flex: 1,
                      child: TextField(
                        controller: middleCtrl,
                        maxLength: 50,
                        textCapitalization: TextCapitalization.words,
                        decoration: const InputDecoration(
                          labelText: 'Middle Name',
                          counterText: '',
                        ),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 12),

                // Last Name
                TextField(
                  controller: lastCtrl,
                  textCapitalization: TextCapitalization.words,
                  decoration: const InputDecoration(labelText: 'Last Name *'),
                ),
                const SizedBox(height: 12),

                // Read-only login email
                TextField(
                  enabled: false,
                  controller: TextEditingController(text: user?.email ?? ''),
                  decoration: const InputDecoration(
                    labelText: 'Login Email Address (Read-only)',
                    prefixIcon: Icon(Icons.lock_outline, size: 18),
                  ),
                ),
                const SizedBox(height: 12),

                // Contact Number
                TextField(
                  controller: contactCtrl,
                  keyboardType: TextInputType.phone,
                  decoration: const InputDecoration(
                    labelText: 'Contact Number *',
                    hintText: '09XXXXXXXXX or +639XXXXXXXXX',
                    prefixIcon: Icon(Icons.phone_android_outlined, size: 20),
                  ),
                ),
                const SizedBox(height: 20),

                ElevatedButton(
                  onPressed: isSaving
                      ? null
                      : () async {
                          final first = firstCtrl.text.trim();
                          final last = lastCtrl.text.trim();
                          String contact = contactCtrl.text.trim();

                          if (first.length < 2) {
                            setSheetState(
                              () => dialogError =
                                  'First name must be at least 2 characters.',
                            );
                            return;
                          }
                          if (last.length < 2) {
                            setSheetState(
                              () => dialogError =
                                  'Last name must be at least 2 characters.',
                            );
                            return;
                          }
                          if (contact.length == 10 && contact.startsWith('9')) {
                            contact = '0$contact';
                          }
                          if (!RegExp(
                            r'^(09\d{9}|\+639\d{9})$',
                          ).hasMatch(contact)) {
                            setSheetState(
                              () => dialogError =
                                  'Please provide a valid Philippine mobile number (09XXXXXXXXX).',
                            );
                            return;
                          }

                          setSheetState(() {
                            isSaving = true;
                            dialogError = null;
                          });

                          final scaffoldMessenger = ScaffoldMessenger.of(
                            context,
                          );
                          final success = await auth.updateProfile({
                            'first_name': first,
                            'middle_name': middleCtrl.text.trim().isNotEmpty
                                ? middleCtrl.text.trim()
                                : null,
                            'last_name': last,
                            'contact_no': contact,
                          });

                          if (ctx.mounted) {
                            Navigator.pop(ctx);
                          }
                          scaffoldMessenger.showSnackBar(
                            SnackBar(
                              content: Text(
                                success
                                    ? 'Profile details saved successfully!'
                                    : (auth.errorMessage ?? 'Update failed.'),
                              ),
                              backgroundColor: success
                                  ? Colors.green
                                  : Colors.red,
                            ),
                          );
                        },
                  child: isSaving
                      ? const SizedBox(
                          width: 20,
                          height: 20,
                          child: CircularProgressIndicator(
                            strokeWidth: 2,
                            color: Colors.white,
                          ),
                        )
                      : const Text('Save Profile Details'),
                ),
                const SizedBox(height: 24),
              ],
            ),
          ),
        ),
      ),
    );
  }

  // 3. CHANGE PASSWORD HANDLER
  Future<void> _submitChangePassword() async {
    final current = _currentPasswordCtrl.text;
    final newPass = _newPasswordCtrl.text;
    final confirmPass = _confirmPasswordCtrl.text;

    if (current.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Please enter your current password.')),
      );
      return;
    }
    if (newPass.length < 8) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('New password must be at least 8 characters.'),
        ),
      );
      return;
    }
    if (current == newPass) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text(
            'New password must be different from your current password.',
          ),
          backgroundColor: Colors.orange,
        ),
      );
      return;
    }
    if (newPass != confirmPass) {
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(const SnackBar(content: Text('Passwords do not match.')));
      return;
    }

    setState(() => _isUpdatingPassword = true);
    final auth = context.read<AuthProvider>();
    final success = await auth.updatePassword(current, newPass);
    if (mounted) {
      setState(() => _isUpdatingPassword = false);
      if (success) {
        _currentPasswordCtrl.clear();
        _newPasswordCtrl.clear();
        _confirmPasswordCtrl.clear();
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Password updated successfully!'),
            backgroundColor: Colors.green,
          ),
        );
      } else {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(auth.errorMessage ?? 'Failed to update password.'),
            backgroundColor: Colors.red,
          ),
        );
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthProvider>();
    final user = auth.user;
    final themeColor = _getThemeColor(user?.profileTheme ?? 'rose');

    return SafeArea(
      child: RefreshIndicator(
        onRefresh: () async {
          await Future.wait([
            auth.refreshUser(),
            _loadPreferences(),
            _loadFollowedShops(),
          ]);
        },
        child: SingleChildScrollView(
          physics: const AlwaysScrollableScrollPhysics(),
          padding: const EdgeInsets.fromLTRB(16, 12, 16, 32),
          child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            // ─────────────────────────────────────────────────────────
            // SECTION 1: PROFILE APPEARANCE ("Make It Yours")
            // ─────────────────────────────────────────────────────────
            Container(
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(16),
                border: Border.all(color: const Color(0xFFEAECEF)),
                boxShadow: [
                  BoxShadow(
                    color: Colors.black.withValues(alpha: 0.03),
                    blurRadius: 10,
                    offset: const Offset(0, 3),
                  ),
                ],
              ),
              clipBehavior: Clip.antiAlias,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  // Cover Banner Image with EZiCart Wordmark
                  Stack(
                    children: [
                      Container(
                        height: 110,
                        width: double.infinity,
                        decoration: BoxDecoration(
                          gradient: LinearGradient(
                            colors: [
                              themeColor.withValues(alpha: 0.85),
                              themeColor,
                            ],
                            begin: Alignment.topLeft,
                            end: Alignment.bottomRight,
                          ),
                        ),
                        child: user?.bannerUrl != null
                            ? CachedNetworkImage(
                                imageUrl: user!.bannerUrl!,
                                fit: BoxFit.cover,
                                errorWidget: (_, __, ___) =>
                                    const SizedBox.shrink(),
                              )
                            : null,
                      ),
                      Positioned(
                        right: 14,
                        top: 10,
                        child: Text(
                          'EZiCart',
                          style: TextStyle(
                            fontFamily: 'Bodoni Moda',
                            fontWeight: FontWeight.bold,
                            fontSize: 16,
                            color: Colors.white.withValues(alpha: 0.35),
                            letterSpacing: 1.5,
                          ),
                        ),
                      ),
                    ],
                  ),

                  // Avatar & Identity row overlapping banner
                  Padding(
                    padding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Transform.translate(
                          offset: const Offset(0, -32),
                          child: Row(
                            crossAxisAlignment: CrossAxisAlignment.end,
                            children: [
                              // Avatar circle
                              Container(
                                width: 72,
                                height: 72,
                                decoration: BoxDecoration(
                                  shape: BoxShape.circle,
                                  color: Colors.white,
                                  border: Border.all(
                                    color: Colors.white,
                                    width: 3,
                                  ),
                                  boxShadow: [
                                    BoxShadow(
                                      color: Colors.black.withValues(
                                        alpha: 0.12,
                                      ),
                                      blurRadius: 8,
                                      offset: const Offset(0, 2),
                                    ),
                                  ],
                                ),
                                child: ClipOval(
                                  child: user?.avatarUrl != null
                                      ? CachedNetworkImage(
                                          imageUrl: user!.avatarUrl!,
                                          fit: BoxFit.cover,
                                          errorWidget: (_, __, ___) =>
                                              _buildAvatarFallback(
                                                user,
                                                themeColor,
                                              ),
                                        )
                                      : _buildAvatarFallback(user, themeColor),
                                ),
                              ),
                              const Spacer(),
                              OutlinedButton.icon(
                                style: OutlinedButton.styleFrom(
                                  padding: const EdgeInsets.symmetric(
                                    horizontal: 12,
                                    vertical: 7,
                                  ),
                                  minimumSize: const Size(60, 34),
                                  side: BorderSide(color: themeColor),
                                  shape: RoundedRectangleBorder(
                                    borderRadius: BorderRadius.circular(20),
                                  ),
                                ),
                                onPressed: _showAppearanceSheet,
                                icon: Icon(
                                  Icons.edit_outlined,
                                  size: 14,
                                  color: themeColor,
                                ),
                                label: Text(
                                  'Edit Appearance',
                                  style: TextStyle(
                                    fontSize: 11.5,
                                    fontWeight: FontWeight.bold,
                                    color: themeColor,
                                  ),
                                ),
                              ),
                            ],
                          ),
                        ),

                        // Display Name, Full Name, and Badge
                        Transform.translate(
                          offset: const Offset(0, -20),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Row(
                                children: [
                                  Flexible(
                                    child: Text(
                                      user?.displayName ?? 'Buyer Account',
                                      style: const TextStyle(
                                        fontWeight: FontWeight.bold,
                                        fontSize: 17,
                                        color: AppTheme.ink,
                                      ),
                                    ),
                                  ),
                                  const SizedBox(width: 8),
                                  Container(
                                    padding: const EdgeInsets.symmetric(
                                      horizontal: 6,
                                      vertical: 2,
                                    ),
                                    decoration: BoxDecoration(
                                      color: themeColor.withValues(alpha: 0.12),
                                      borderRadius: BorderRadius.circular(4),
                                    ),
                                    child: Text(
                                      'VERIFIED BUYER',
                                      style: TextStyle(
                                        color: themeColor,
                                        fontSize: 9.5,
                                        fontWeight: FontWeight.bold,
                                      ),
                                    ),
                                  ),
                                ],
                              ),
                              if (user?.profileDisplayName != null &&
                                  user!.profileDisplayName!.isNotEmpty) ...[
                                const SizedBox(height: 2),
                                Text(
                                  user.fullName,
                                  style: const TextStyle(
                                    fontSize: 12,
                                    color: AppTheme.muted,
                                  ),
                                ),
                              ],
                              const SizedBox(height: 6),
                              Text(
                                (user?.profileBio != null &&
                                        user!.profileBio!.isNotEmpty)
                                    ? user.profileBio!
                                    : 'A short introduction for your personal profile.',
                                style: TextStyle(
                                  fontSize: 12.5,
                                  color:
                                      (user?.profileBio != null &&
                                          user!.profileBio!.isNotEmpty)
                                      ? const Color(0xFF334155)
                                      : AppTheme.muted,
                                  fontStyle:
                                      (user?.profileBio != null &&
                                          user!.profileBio!.isNotEmpty)
                                      ? FontStyle.normal
                                      : FontStyle.italic,
                                  height: 1.3,
                                ),
                              ),
                            ],
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 16),

            // ─────────────────────────────────────────────────────────
            // SECTION 2: SHOPS YOU FOLLOW ("following-shops")
            // ─────────────────────────────────────────────────────────
            Container(
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(16),
                border: Border.all(color: const Color(0xFFEAECEF)),
              ),
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      const Row(
                        children: [
                          Icon(
                            Icons.storefront_outlined,
                            color: AppTheme.primary,
                            size: 19,
                          ),
                          SizedBox(width: 8),
                          Text(
                            'Shops You Follow',
                            style: TextStyle(
                              fontWeight: FontWeight.bold,
                              fontSize: 14.5,
                            ),
                          ),
                        ],
                      ),
                      if (_followedShops.isNotEmpty)
                        Text(
                          '${_followedShops.length} followed',
                          style: const TextStyle(
                            fontSize: 12,
                            color: AppTheme.muted,
                            fontWeight: FontWeight.w500,
                          ),
                        ),
                    ],
                  ),
                  const SizedBox(height: 4),
                  const Text(
                    'Your followed active shops, most recent first.',
                    style: TextStyle(fontSize: 11.5, color: AppTheme.muted),
                  ),
                  const SizedBox(height: 12),

                  if (_isLoadingFollowedShops)
                    const Padding(
                      padding: EdgeInsets.all(12),
                      child: Center(
                        child: CircularProgressIndicator(strokeWidth: 2),
                      ),
                    )
                  else if (_followedShops.isEmpty)
                    Container(
                      width: double.infinity,
                      padding: const EdgeInsets.all(14),
                      decoration: BoxDecoration(
                        color: const Color(0xFFF8FAFC),
                        borderRadius: BorderRadius.circular(10),
                        border: Border.all(color: const Color(0xFFE2E8F0)),
                      ),
                      child: const Row(
                        children: [
                          Icon(
                            Icons.bookmark_border_outlined,
                            color: AppTheme.muted,
                            size: 20,
                          ),
                          SizedBox(width: 10),
                          Expanded(
                            child: Text(
                              'You have not followed any shops yet. Visit stores to stay updated on new arrivals.',
                              style: TextStyle(
                                fontSize: 12,
                                color: AppTheme.muted,
                              ),
                            ),
                          ),
                        ],
                      ),
                    )
                  else
                    SizedBox(
                      height: 105,
                      child: ListView.separated(
                        scrollDirection: Axis.horizontal,
                        itemCount: _followedShops.length,
                        separatorBuilder: (_, __) => const SizedBox(width: 10),
                        itemBuilder: (context, i) {
                          final shop = _followedShops[i];
                          final shopId = shop['id'] as int? ?? 0;
                          final shopName = shop['name']?.toString() ?? 'Shop';
                          final location =
                              shop['location']?.toString() ?? 'Laguna';
                          final productCount = shop['product_count'] ?? 0;

                          return InkWell(
                            onTap: () async {
                              await Navigator.of(context).push(
                                MaterialPageRoute(
                                  builder: (_) => ShopPage(
                                    sellerId: shopId,
                                    initialShopName: shopName,
                                  ),
                                ),
                              );
                              if (mounted) {
                                _loadFollowedShops();
                              }
                            },
                            borderRadius: BorderRadius.circular(12),
                            child: Container(
                              width: 155,
                              padding: const EdgeInsets.all(10),
                              decoration: BoxDecoration(
                                color: const Color(0xFFF9FAFB),
                                borderRadius: BorderRadius.circular(12),
                                border: Border.all(
                                  color: const Color(0xFFEAECEF),
                                ),
                              ),
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Row(
                                    children: [
                                      ClipOval(
                                        child: Container(
                                          width: 28,
                                          height: 28,
                                          decoration: const BoxDecoration(
                                            color: AppTheme.primarySoft,
                                            shape: BoxShape.circle,
                                          ),
                                          child: (shop['avatar_url'] != null && shop['avatar_url'].toString().isNotEmpty)
                                              ? CachedNetworkImage(
                                                  imageUrl: ApiConfig.resolveImageUrl(shop['avatar_url'].toString())!,
                                                  fit: BoxFit.cover,
                                                  placeholder: (_, __) => Container(color: AppTheme.primarySoft),
                                                  errorWidget: (_, __, ___) => Center(
                                                    child: Text(
                                                      shopName.isNotEmpty ? shopName[0].toUpperCase() : 'S',
                                                      style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13, color: AppTheme.primary),
                                                    ),
                                                  ),
                                                )
                                              : Center(
                                                  child: Text(
                                                    shopName.isNotEmpty ? shopName[0].toUpperCase() : 'S',
                                                    style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13, color: AppTheme.primary),
                                                  ),
                                                ),
                                        ),
                                      ),
                                      const Spacer(),
                                      const Icon(
                                        Icons.arrow_forward_ios,
                                        size: 11,
                                        color: AppTheme.muted,
                                      ),
                                    ],
                                  ),
                                  const Spacer(),
                                  Text(
                                    shopName,
                                    maxLines: 1,
                                    overflow: TextOverflow.ellipsis,
                                    style: const TextStyle(
                                      fontWeight: FontWeight.bold,
                                      fontSize: 12,
                                      color: AppTheme.ink,
                                    ),
                                  ),
                                  Text(
                                    '$location • $productCount items',
                                    maxLines: 1,
                                    overflow: TextOverflow.ellipsis,
                                    style: const TextStyle(
                                      fontSize: 10,
                                      color: AppTheme.muted,
                                    ),
                                  ),
                                ],
                              ),
                            ),
                          );
                        },
                      ),
                    ),
                ],
              ),
            ),
            const SizedBox(height: 16),

            // Finance & Wallet Navigation Card
            InkWell(
              onTap: () {
                Navigator.of(context).push(
                  MaterialPageRoute(builder: (_) => const WalletPage()),
                );
              },
              borderRadius: BorderRadius.circular(16),
              child: Container(
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(16),
                  border: Border.all(color: const Color(0xFFEAECEF)),
                ),
                padding: const EdgeInsets.all(16),
                child: Row(
                  children: [
                    Container(
                      padding: const EdgeInsets.all(10),
                      decoration: BoxDecoration(
                        color: Colors.green.shade50,
                        borderRadius: BorderRadius.circular(12),
                      ),
                      child: Icon(
                        Icons.account_balance_wallet,
                        color: Colors.green.shade700,
                        size: 24,
                      ),
                    ),
                    const SizedBox(width: 14),
                    const Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            'Finance & Wallet',
                            style: TextStyle(
                              fontWeight: FontWeight.bold,
                              fontSize: 14.5,
                              color: AppTheme.ink,
                            ),
                          ),
                          SizedBox(height: 2),
                          Text(
                            'Manage demo balance, view transactions, funding and records.',
                            style: TextStyle(
                              fontSize: 11.5,
                              color: AppTheme.muted,
                            ),
                          ),
                        ],
                      ),
                    ),
                    const Icon(
                      Icons.chevron_right,
                      color: AppTheme.muted,
                      size: 20,
                    ),
                  ],
                ),
              ),
            ),
            const SizedBox(height: 16),

            // My Wishlist Quick Navigation Card
            InkWell(
              onTap: () {
                Navigator.of(context).push(
                  MaterialPageRoute(builder: (_) => const WishlistPage()),
                );
              },
              borderRadius: BorderRadius.circular(16),
              child: Container(
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(16),
                  border: Border.all(color: const Color(0xFFEAECEF)),
                ),
                padding: const EdgeInsets.all(16),
                child: Row(
                  children: [
                    Container(
                      padding: const EdgeInsets.all(10),
                      decoration: BoxDecoration(
                        color: AppTheme.primarySoft,
                        borderRadius: BorderRadius.circular(12),
                      ),
                      child: const Icon(
                        Icons.favorite,
                        color: AppTheme.primary,
                        size: 24,
                      ),
                    ),
                    const SizedBox(width: 14),
                    const Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            'My Wishlist',
                            style: TextStyle(
                              fontWeight: FontWeight.bold,
                              fontSize: 14.5,
                              color: AppTheme.ink,
                            ),
                          ),
                          SizedBox(height: 2),
                          Text(
                            'View saved products, monitor stock and price updates.',
                            style: TextStyle(
                              fontSize: 11.5,
                              color: AppTheme.muted,
                            ),
                          ),
                        ],
                      ),
                    ),
                    const Icon(
                      Icons.chevron_right,
                      color: AppTheme.muted,
                      size: 20,
                    ),
                  ],
                ),
              ),
            ),
            const SizedBox(height: 16),

            // ─────────────────────────────────────────────────────────
            // SECTION 3: PROFILE DETAILS ("buyer-profile")
            // ─────────────────────────────────────────────────────────
            Container(
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(16),
                border: Border.all(color: const Color(0xFFEAECEF)),
              ),
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      const Row(
                        children: [
                          Icon(
                            Icons.person_outline,
                            color: AppTheme.primary,
                            size: 20,
                          ),
                          SizedBox(width: 8),
                          Text(
                            'Profile Details',
                            style: TextStyle(
                              fontWeight: FontWeight.bold,
                              fontSize: 14.5,
                            ),
                          ),
                        ],
                      ),
                      TextButton.icon(
                        style: TextButton.styleFrom(
                          padding: EdgeInsets.zero,
                          visualDensity: VisualDensity.compact,
                        ),
                        onPressed: _showEditProfileDialog,
                        icon: const Icon(
                          Icons.edit_outlined,
                          size: 14,
                          color: AppTheme.primary,
                        ),
                        label: const Text(
                          'Edit',
                          style: TextStyle(
                            fontSize: 12,
                            color: AppTheme.primary,
                            fontWeight: FontWeight.bold,
                          ),
                        ),
                      ),
                    ],
                  ),
                  const Text(
                    'The contact and personal information connected to your account.',
                    style: TextStyle(fontSize: 11.5, color: AppTheme.muted),
                  ),
                  const SizedBox(height: 12),

                  _buildDetailRow(
                    Icons.badge_outlined,
                    'Full Name',
                    user?.fullName ?? 'Not set',
                  ),
                  const Divider(height: 16, color: Color(0xFFF1F3F6)),
                  _buildDetailRow(
                    Icons.email_outlined,
                    'Login Email',
                    user?.email ?? 'Not set',
                    badge: 'READ-ONLY',
                  ),
                  const Divider(height: 16, color: Color(0xFFF1F3F6)),
                  _buildDetailRow(
                    Icons.phone_android_outlined,
                    'Contact Number',
                    user?.contactNo ?? 'Not set',
                  ),
                  const Divider(height: 16, color: Color(0xFFF1F3F6)),
                  _buildDetailRow(
                    Icons.location_on_outlined,
                    'Default Address',
                    user?.fullAddress ?? 'No registered address',
                    actionLabel: 'Manage Addresses',
                    onAction: () {
                      Navigator.of(context).push(
                        MaterialPageRoute(
                          builder: (_) => const AddressesPage(),
                        ),
                      );
                    },
                  ),
                ],
              ),
            ),
            const SizedBox(height: 16),

            // ─────────────────────────────────────────────────────────
            // SECTION 4: CHANGE PASSWORD ("buyer-password")
            // ─────────────────────────────────────────────────────────
            Container(
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(16),
                border: Border.all(color: const Color(0xFFEAECEF)),
              ),
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Row(
                    children: [
                      Icon(
                        Icons.lock_outline,
                        color: AppTheme.primary,
                        size: 20,
                      ),
                      SizedBox(width: 8),
                      Text(
                        'Change Password',
                        style: TextStyle(
                          fontWeight: FontWeight.bold,
                          fontSize: 14.5,
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 4),
                  const Text(
                    'Use a unique password (min 8 chars) to protect your EZiCart account.',
                    style: TextStyle(fontSize: 11.5, color: AppTheme.muted),
                  ),
                  const SizedBox(height: 14),

                  // Current Password
                  TextField(
                    controller: _currentPasswordCtrl,
                    obscureText: _obscureCurrent,
                    decoration: InputDecoration(
                      labelText: 'Current Password',
                      contentPadding: const EdgeInsets.symmetric(
                        horizontal: 12,
                        vertical: 10,
                      ),
                      suffixIcon: IconButton(
                        icon: Icon(
                          _obscureCurrent
                              ? Icons.visibility_off
                              : Icons.visibility,
                          size: 18,
                        ),
                        onPressed: () =>
                            setState(() => _obscureCurrent = !_obscureCurrent),
                      ),
                    ),
                  ),
                  const SizedBox(height: 10),

                  // New Password
                  TextField(
                    controller: _newPasswordCtrl,
                    obscureText: _obscureNew,
                    decoration: InputDecoration(
                      labelText: 'New Password',
                      hintText:
                          'Min 8 characters with letters, numbers, symbols',
                      contentPadding: const EdgeInsets.symmetric(
                        horizontal: 12,
                        vertical: 10,
                      ),
                      suffixIcon: IconButton(
                        icon: Icon(
                          _obscureNew ? Icons.visibility_off : Icons.visibility,
                          size: 18,
                        ),
                        onPressed: () =>
                            setState(() => _obscureNew = !_obscureNew),
                      ),
                    ),
                  ),
                  const SizedBox(height: 10),

                  // Confirm New Password
                  TextField(
                    controller: _confirmPasswordCtrl,
                    obscureText: _obscureConfirm,
                    decoration: InputDecoration(
                      labelText: 'Confirm New Password',
                      contentPadding: const EdgeInsets.symmetric(
                        horizontal: 12,
                        vertical: 10,
                      ),
                      suffixIcon: IconButton(
                        icon: Icon(
                          _obscureConfirm
                              ? Icons.visibility_off
                              : Icons.visibility,
                          size: 18,
                        ),
                        onPressed: () =>
                            setState(() => _obscureConfirm = !_obscureConfirm),
                      ),
                    ),
                  ),
                  const SizedBox(height: 14),

                  Align(
                    alignment: Alignment.centerRight,
                    child: ElevatedButton(
                      style: ElevatedButton.styleFrom(
                        padding: const EdgeInsets.symmetric(
                          horizontal: 18,
                          vertical: 10,
                        ),
                        minimumSize: const Size(130, 40),
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(8),
                        ),
                      ),
                      onPressed: _isUpdatingPassword
                          ? null
                          : _submitChangePassword,
                      child: _isUpdatingPassword
                          ? const SizedBox(
                              width: 18,
                              height: 18,
                              child: CircularProgressIndicator(
                                strokeWidth: 2,
                                color: Colors.white,
                              ),
                            )
                          : const Text(
                              'Update Password',
                              style: TextStyle(
                                fontSize: 12.5,
                                fontWeight: FontWeight.bold,
                              ),
                            ),
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 16),

            // ─────────────────────────────────────────────────────────
            // SECTION 5: ACCOUNT PREFERENCES & SIGNOUT
            // ─────────────────────────────────────────────────────────
            Container(
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(16),
                border: Border.all(color: const Color(0xFFEAECEF)),
              ),
              child: Column(
                children: [
                  SwitchListTile(
                    secondary: const Icon(
                      Icons.campaign_outlined,
                      color: AppTheme.primary,
                      size: 22,
                    ),
                    title: const Text(
                      'Store Broadcasts & Offers',
                      style: TextStyle(
                        fontSize: 13.5,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                    subtitle: const Text(
                      'Promotional alerts and restock campaigns from followed stores',
                      style: TextStyle(fontSize: 11, color: AppTheme.muted),
                    ),
                    value: _promotionsEnabled,
                    activeTrackColor: AppTheme.primary,
                    onChanged: _updatePromotions,
                  ),
                ],
              ),
            ),
            const SizedBox(height: 20),

            // Sign Out Button
            OutlinedButton.icon(
              style: OutlinedButton.styleFrom(
                foregroundColor: const Color(0xFFDC2626),
                side: const BorderSide(color: Color(0xFFFECACA)),
                minimumSize: const Size(double.infinity, 46),
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(10),
                ),
              ),
              icon: const Icon(Icons.logout_rounded, size: 18),
              label: const Text(
                'Sign Out from Account',
                style: TextStyle(fontWeight: FontWeight.bold, fontSize: 13.5),
              ),
              onPressed: () {
                showDialog(
                  context: context,
                  builder: (ctx) => AlertDialog(
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(14),
                    ),
                    title: const Text('Sign Out'),
                    content: const Text(
                      'Are you sure you want to sign out from this device?',
                    ),
                    actions: [
                      TextButton(
                        onPressed: () => Navigator.pop(ctx),
                        child: const Text('Cancel'),
                      ),
                      ElevatedButton(
                        style: ElevatedButton.styleFrom(
                          backgroundColor: Colors.red,
                          shape: RoundedRectangleBorder(
                            borderRadius: BorderRadius.circular(8),
                          ),
                        ),
                        onPressed: () {
                          Navigator.pop(ctx);
                          context.read<AuthProvider>().logout();
                        },
                        child: const Text('Sign Out'),
                      ),
                    ],
                  ),
                );
              },
            ),
          ],
        ),
      ),
    ),
  );
  }

  Widget _buildAvatarFallback(UserModel? user, Color themeColor) {
    final initials =
        (user != null && user.firstName.isNotEmpty && user.lastName.isNotEmpty)
        ? '${user.firstName[0]}${user.lastName[0]}'.toUpperCase()
        : 'B';
    return Container(
      color: themeColor.withValues(alpha: 0.15),
      child: Center(
        child: Text(
          initials,
          style: TextStyle(
            fontSize: 24,
            fontWeight: FontWeight.bold,
            color: themeColor,
          ),
        ),
      ),
    );
  }

  Widget _buildDetailRow(
    IconData icon,
    String label,
    String value, {
    String? badge,
    String? actionLabel,
    VoidCallback? onAction,
  }) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Icon(icon, size: 18, color: AppTheme.muted),
        const SizedBox(width: 10),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Text(
                    label,
                    style: const TextStyle(
                      fontSize: 11.5,
                      color: AppTheme.muted,
                    ),
                  ),
                  if (badge != null) ...[
                    const SizedBox(width: 6),
                    Container(
                      padding: const EdgeInsets.symmetric(
                        horizontal: 5,
                        vertical: 1,
                      ),
                      decoration: BoxDecoration(
                        color: Colors.grey.shade200,
                        borderRadius: BorderRadius.circular(3),
                      ),
                      child: Text(
                        badge,
                        style: const TextStyle(
                          fontSize: 8.5,
                          fontWeight: FontWeight.bold,
                          color: AppTheme.muted,
                        ),
                      ),
                    ),
                  ],
                ],
              ),
              const SizedBox(height: 2),
              Text(
                value,
                style: const TextStyle(
                  fontSize: 13,
                  fontWeight: FontWeight.w600,
                  color: AppTheme.ink,
                ),
              ),
              if (actionLabel != null && onAction != null) ...[
                const SizedBox(height: 4),
                InkWell(
                  onTap: onAction,
                  child: Text(
                    actionLabel,
                    style: const TextStyle(
                      fontSize: 11.5,
                      color: AppTheme.primary,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                ),
              ],
            ],
          ),
        ),
      ],
    );
  }
}
