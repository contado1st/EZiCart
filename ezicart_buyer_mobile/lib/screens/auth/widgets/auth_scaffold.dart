import 'package:flutter/material.dart';
import '../../../config/theme.dart';

class AuthScaffold extends StatelessWidget {
  final String title;
  final String subtitle;
  final bool showBack;
  final VoidCallback? onBack;
  final Widget? topAction;
  final Widget cardChild;
  final Widget? cardBottom;

  const AuthScaffold({
    super.key,
    required this.title,
    required this.subtitle,
    this.showBack = false,
    this.onBack,
    this.topAction,
    required this.cardChild,
    this.cardBottom,
  });

  @override
  Widget build(BuildContext context) {
    // Exact physical viewport dimensions (never shrinks when keyboard opens)
    final screenSize = MediaQuery.sizeOf(context);

    return Scaffold(
      backgroundColor: const Color(0xFFD63363),
      body: Stack(
        fit: StackFit.expand,
        children: [
          // 1. STATIC Background Image locked to full viewport dimensions
          // Explicit width & height prevents any movement or scaling when keyboard opens
          Positioned(
            top: 0,
            left: 0,
            width: screenSize.width,
            height: screenSize.height,
            child: IgnorePointer(
              child: Image.asset(
                'assets/images/ezicart-background.png',
                fit: BoxFit.cover,
                alignment: Alignment.center,
                errorBuilder: (_, __, ___) => Container(
                  decoration: const BoxDecoration(
                    gradient: LinearGradient(
                      begin: Alignment.topCenter,
                      end: Alignment.bottomCenter,
                      colors: [
                        Color(0xFFFF7597),
                        Color(0xFFD63363),
                        Color(0xFFBE2854),
                      ],
                    ),
                  ),
                ),
              ),
            ),
          ),

          // 2. STATIC Ambient Depth / Darkening Layer
          // Darkens the background slightly so the foreground card and logo pop with crisp visual separation
          Positioned(
            top: 0,
            left: 0,
            width: screenSize.width,
            height: screenSize.height,
            child: IgnorePointer(
              child: Container(
                decoration: BoxDecoration(
                  gradient: LinearGradient(
                    begin: Alignment.topCenter,
                    end: Alignment.bottomCenter,
                    colors: [
                      Colors.black.withValues(alpha: 0.32),
                      Colors.black.withValues(alpha: 0.22),
                      Colors.black.withValues(alpha: 0.45),
                    ],
                    stops: const [0.0, 0.45, 1.0],
                  ),
                ),
              ),
            ),
          ),

          // 3. Centered Scrollable Content
          SafeArea(
            child: LayoutBuilder(
              builder: (context, constraints) {
                return SingleChildScrollView(
                  physics: const AlwaysScrollableScrollPhysics(
                    parent: BouncingScrollPhysics(),
                  ),
                  keyboardDismissBehavior:
                      ScrollViewKeyboardDismissBehavior.onDrag,
                  child: ConstrainedBox(
                    constraints: BoxConstraints(
                      minHeight: constraints.maxHeight,
                    ),
                    child: Center(
                      child: Container(
                        constraints: const BoxConstraints(maxWidth: 440),
                        padding: const EdgeInsets.fromLTRB(20, 16, 20, 24),
                        child: Column(
                          mainAxisAlignment: MainAxisAlignment.center,
                          crossAxisAlignment: CrossAxisAlignment.center,
                          children: [
                            const SizedBox(height: 8),

                            // Large, Prominent & Beautifully Balanced EZiCart Logo
                            Hero(
                              tag: 'ezicart-app-logo',
                              child: Container(
                                padding: const EdgeInsets.symmetric(
                                  horizontal: 24,
                                  vertical: 12,
                                ),
                                decoration: BoxDecoration(
                                  color: Colors.white.withValues(alpha: 0.16),
                                  borderRadius: BorderRadius.circular(22),
                                  border: Border.all(
                                    color: Colors.white.withValues(alpha: 0.35),
                                    width: 1.5,
                                  ),
                                  boxShadow: [
                                    BoxShadow(
                                      color: Colors.black.withValues(
                                        alpha: 0.28,
                                      ),
                                      blurRadius: 18,
                                      offset: const Offset(0, 6),
                                    ),
                                    BoxShadow(
                                      color: const Color(
                                        0xFFBE2854,
                                      ).withValues(alpha: 0.25),
                                      blurRadius: 28,
                                      offset: const Offset(0, 4),
                                    ),
                                  ],
                                ),
                                child: Image.asset(
                                  'assets/images/ezicart-logo.png',
                                  height: 60,
                                  fit: BoxFit.contain,
                                  errorBuilder: (_, __, ___) => const Row(
                                    mainAxisSize: MainAxisSize.min,
                                    children: [
                                      Icon(
                                        Icons.shopping_bag_outlined,
                                        color: Colors.white,
                                        size: 28,
                                      ),
                                      SizedBox(width: 10),
                                      Text(
                                        'EZiCart',
                                        style: TextStyle(
                                          color: Colors.white,
                                          fontSize: 26,
                                          fontWeight: FontWeight.bold,
                                          letterSpacing: 0.8,
                                        ),
                                      ),
                                    ],
                                  ),
                                ),
                              ),
                            ),
                            const SizedBox(height: 22),

                            // Clean, Self-Contained Floating Card
                            // (Houses Title, Description, Inputs, Action Buttons, and Bottom Links)
                            Container(
                              width: double.infinity,
                              padding: const EdgeInsets.fromLTRB(
                                22,
                                26,
                                22,
                                24,
                              ),
                              decoration: BoxDecoration(
                                color: Colors.white,
                                borderRadius: BorderRadius.circular(24),
                                boxShadow: [
                                  BoxShadow(
                                    color: Colors.black.withValues(alpha: 0.30),
                                    blurRadius: 32,
                                    offset: const Offset(0, 14),
                                  ),
                                ],
                              ),
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.stretch,
                                children: [
                                  // Centered Title inside the Card
                                  Text(
                                    title,
                                    textAlign: TextAlign.center,
                                    style: const TextStyle(
                                      fontSize: 22,
                                      fontWeight: FontWeight.w800,
                                      color: AppTheme.ink,
                                      letterSpacing: -0.3,
                                    ),
                                  ),
                                  const SizedBox(height: 6),

                                  // Centered Description inside the Card
                                  Text(
                                    subtitle,
                                    textAlign: TextAlign.center,
                                    style: const TextStyle(
                                      fontSize: 13,
                                      color: AppTheme.muted,
                                      height: 1.35,
                                    ),
                                  ),
                                  const SizedBox(height: 20),

                                  // Card Form Content
                                  cardChild,

                                  // Bottom Link inside the Card
                                  if (cardBottom != null) ...[
                                    const SizedBox(height: 18),
                                    const Divider(
                                      color: Color(0xFFEDF2F7),
                                      height: 1,
                                    ),
                                    const SizedBox(height: 14),
                                    cardBottom!,
                                  ],
                                ],
                              ),
                            ),

                            const SizedBox(height: 16),
                          ],
                        ),
                      ),
                    ),
                  ),
                );
              },
            ),
          ),

          // 4. Floating Top Actions (Back Button on left, Settings gear on right)
          if (showBack)
            Positioned(
              top: 0,
              left: 16,
              child: SafeArea(
                child: Padding(
                  padding: const EdgeInsets.only(top: 8),
                  child: Material(
                    color: Colors.black.withValues(alpha: 0.35),
                    borderRadius: BorderRadius.circular(12),
                    child: InkWell(
                      borderRadius: BorderRadius.circular(12),
                      onTap: onBack ?? () => Navigator.of(context).maybePop(),
                      child: Container(
                        padding: const EdgeInsets.all(8),
                        decoration: BoxDecoration(
                          borderRadius: BorderRadius.circular(12),
                          border: Border.all(
                            color: Colors.white.withValues(alpha: 0.30),
                            width: 1,
                          ),
                        ),
                        child: const Icon(
                          Icons.arrow_back_ios_new,
                          color: Colors.white,
                          size: 16,
                        ),
                      ),
                    ),
                  ),
                ),
              ),
            ),

          if (topAction != null)
            Positioned(
              top: 0,
              right: 16,
              child: SafeArea(
                child: Padding(
                  padding: const EdgeInsets.only(top: 8),
                  child: topAction!,
                ),
              ),
            ),
        ],
      ),
    );
  }
}
