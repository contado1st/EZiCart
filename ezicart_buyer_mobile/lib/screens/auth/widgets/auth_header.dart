import 'package:flutter/material.dart';
import '../../../config/theme.dart';

class AuthHeader extends StatelessWidget {
  final String title;
  final String subtitle;
  final Widget? topAction;
  final bool showBack;
  final VoidCallback? onBack;
  final double headerHeight;

  const AuthHeader({
    super.key,
    required this.title,
    required this.subtitle,
    this.topAction,
    this.showBack = false,
    this.onBack,
    this.headerHeight = 220,
  });

  @override
  Widget build(BuildContext context) {
    final topPadding = MediaQuery.of(context).padding.top;

    return Stack(
      children: [
        // Background Image with Gradient Overlay
        Positioned.fill(
          child: Image.asset(
            'assets/images/ezicart-background.png',
            fit: BoxFit.cover,
            errorBuilder: (_, __, ___) => Container(
              color: AppTheme.primary,
            ),
          ),
        ),
        Positioned.fill(
          child: Container(
            decoration: BoxDecoration(
              gradient: LinearGradient(
                begin: Alignment.topCenter,
                end: Alignment.bottomCenter,
                colors: [
                  const Color(0xFF6E0D27).withValues(alpha: 0.88),
                  const Color(0xFF9F2046).withValues(alpha: 0.92),
                  const Color(0xFFBE2854).withValues(alpha: 0.96),
                ],
              ),
            ),
          ),
        ),

        // Header Content
        Container(
          height: headerHeight + topPadding,
          padding: EdgeInsets.fromLTRB(20, topPadding + 8, 20, 24),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              // Top Bar with Back & Action
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  if (showBack)
                    Material(
                      color: Colors.white.withValues(alpha: 0.18),
                      borderRadius: BorderRadius.circular(12),
                      child: InkWell(
                        borderRadius: BorderRadius.circular(12),
                        onTap: onBack ?? () => Navigator.of(context).maybePop(),
                        child: const Padding(
                          padding: EdgeInsets.all(8),
                          child: Icon(Icons.arrow_back_ios_new, color: Colors.white, size: 18),
                        ),
                      ),
                    )
                  else
                    const SizedBox(width: 40),

                  // Brand Logo
                  Hero(
                    tag: 'ezicart-app-logo',
                    child: Image.asset(
                      'assets/images/ezicart-logo.png',
                      height: 42,
                      fit: BoxFit.contain,
                      errorBuilder: (_, __, ___) => const Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          Icon(Icons.shopping_bag_outlined, color: Colors.white, size: 24),
                          SizedBox(width: 6),
                          Text(
                            'EZiCart',
                            style: TextStyle(
                              color: Colors.white,
                              fontSize: 22,
                              fontWeight: FontWeight.bold,
                              letterSpacing: 0.5,
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),

                  if (topAction != null) topAction! else const SizedBox(width: 40),
                ],
              ),

              // Title & Subtitle
              Column(
                crossAxisAlignment: CrossAxisAlignment.center,
                children: [
                  Text(
                    title,
                    textAlign: TextAlign.center,
                    style: const TextStyle(
                      color: Colors.white,
                      fontSize: 22,
                      fontWeight: FontWeight.bold,
                      letterSpacing: -0.3,
                    ),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    subtitle,
                    textAlign: TextAlign.center,
                    style: TextStyle(
                      color: Colors.white.withValues(alpha: 0.88),
                      fontSize: 13,
                      fontWeight: FontWeight.w400,
                    ),
                  ),
                ],
              ),
            ],
          ),
        ),
      ],
    );
  }
}
